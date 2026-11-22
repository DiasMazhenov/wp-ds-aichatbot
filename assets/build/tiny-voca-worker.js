/*
 * Modified from Tiny Voca RU's browser worker, pinned to the upstream Hugging Face
 * Space revision below. Local changes resolve model assets against that revision.
 * See THIRD_PARTY_NOTICES.md for upstream attribution and Apache-2.0 terms.
 */
const modelBase = "https://huggingface.co/spaces/egnch/tiny-voca-ru-demo/resolve/bf78c265c4cd02a7853d1bcf1d8bcc1fa72aa69e/";
const fetchResource = self.fetch.bind(self);
self.fetch = (resource, options) => fetchResource(
  typeof resource === "string" && resource.startsWith("assets/") ? new URL(resource, modelBase) : resource,
  options
);

// Runs the Russian frontend (tiny_voca_ru.frontend via Pyodide) and ONNX models
// (ONNX Runtime Web) off the main thread. Messages in: {type: "load"} and
// {type: "speak", id, text, voice, speed}. Messages out: status, ready, sentence,
// done, error.

const PYODIDE = "https://cdn.jsdelivr.net/pyodide/v0.26.4/full/";
const ORT = "https://cdn.jsdelivr.net/npm/onnxruntime-web@1.20.1/dist/";

importScripts(PYODIDE + "pyodide.js", ORT + "ort.wasm.min.js");
ort.env.wasm.wasmPaths = ORT;
ort.env.wasm.numThreads = 1;

let pyodide, phonemize, unknownWords, learnStress, homographQueries, accentuateSentence;
let session, accent, omograph, config;

const status = (text, progress) => postMessage({ type: "status", text, progress });
const downloads = new Map();
function reportDownloads(label) {
  let received = 0;
  let total = 0;
  for (const item of downloads.values()) {
    received += item.received;
    total += item.total;
  }
  status(label, total ? received / total : undefined);
}

// Bump when files under assets/ change so browsers refresh cached assets.
const VERSION = "ru-3";

async function download(url, label) {
  const key = `${url}?v=${VERSION}`;
  let store = null;
  try {
    store = await caches.open("tiny-voca-ru-" + VERSION);
    const hit = await store.match(key);
    if (hit) return new Uint8Array(await hit.arrayBuffer());
  } catch (error) { store = null; }
  const bytes = await fetchBytes(key, label);
  if (store) store.put(key, new Response(bytes)).catch(() => {});
  return bytes;
}

async function fetchBytes(url, label) {
  const response = await fetch(url);
  if (!response.ok) throw new Error(`${url}: ${response.status}`);
  const total = Number(response.headers.get("content-length")) || 0;
  if (!response.body || !total) return new Uint8Array(await response.arrayBuffer());
  const reader = response.body.getReader();
  const chunks = [];
  let received = 0;
  downloads.set(url, { received: 0, total });
  for (;;) {
    const { done, value } = await reader.read();
    if (done) break;
    chunks.push(value);
    received += value.length;
    downloads.set(url, { received, total });
    reportDownloads(label);
  }
  const bytes = new Uint8Array(received);
  let offset = 0;
  for (const chunk of chunks) { bytes.set(chunk, offset); offset += chunk.length; }
  return bytes;
}

async function loadPhonemizer() {
  status("Загрузка фонетизатора");
  pyodide = await loadPyodide({ indexURL: PYODIDE });
  const source = await download("assets/phonemizer.zip", "Загрузка фонетизатора и словаря ударений");
  status("Запуск фонетизатора");
  pyodide.unpackArchive(source.buffer, "zip", { extractDir: "/phonemizer" });
  pyodide.runPython(`
import json, sys
sys.path.insert(0, "/phonemizer")
from tiny_voca_ru import frontend

provider = frontend.stress_provider()

def phonemize_json(sentence, stressed=False):
    try:
        result = frontend.phonemize(sentence, stressed=stressed)
    except frontend.NothingToPronounce:
        return "null"
    return json.dumps(
        {
            "ids": list(result.ids),
            "stress": list(result.stress),
            "text": frontend.transcribe(sentence, stressed=stressed),
        },
        ensure_ascii=False,
    )

def unknown_json(raw):
    return json.dumps(
        [[word, frontend.accent_input_ids(word)] for word in frontend.unknown_words(raw, provider)],
        ensure_ascii=False,
    )

def learn_stress(word, logits):
    provider.extra[word] = frontend.stress_from_logits(word, json.loads(logits))

def homographs_json(sentence):
    normalized = frontend.normalize(sentence)
    queries = [
        [start, end, candidates, frontend.omograph_input_ids(normalized, start, end, candidates)]
        for start, end, candidates in provider.homographs(normalized)
    ]
    return json.dumps({"text": normalized, "queries": queries}, ensure_ascii=False)

def accentuate_json(normalized, resolved_json):
    overrides = {
        (int(start), int(end)): reading
        for start, end, reading in json.loads(resolved_json)
    }
    return provider.accentuate_with(normalized, overrides)

phonemize_json("Разминка.")
`);
  phonemize = pyodide.globals.get("phonemize_json");
  unknownWords = pyodide.globals.get("unknown_json");
  learnStress = pyodide.globals.get("learn_stress");
  homographQueries = pyodide.globals.get("homographs_json");
  accentuateSentence = pyodide.globals.get("accentuate_json");
}

async function loadModel() {
  const metaResponse = await fetch(`assets/config.json?v=${VERSION}`);
  if (!metaResponse.ok) throw new Error(`config.json: ${metaResponse.status}`);
  config = await metaResponse.json();
  const bytes = await download("assets/model.onnx", "Загрузка модели");
  status("Запуск модели");
  session = await ort.InferenceSession.create(bytes, { executionProviders: ["wasm"] });
}

// Character accent model for OOV words (onnxruntime is not available inside Pyodide).
async function loadAccent() {
  const bytes = await download("assets/accent.onnx", "Загрузка модели ударений");
  accent = await ort.InferenceSession.create(bytes, { executionProviders: ["wasm"] });
}

// INT8 tiny2.1 contextual homograph resolver.
async function loadOmograph() {
  const bytes = await download("assets/omograph.onnx", "Загрузка модели омографов");
  omograph = await ort.InferenceSession.create(bytes, { executionProviders: ["wasm"] });
}

async function stressUnknownWords(text) {
  for (const [word, ids] of JSON.parse(unknownWords(text))) {
    const integers = (values) => new ort.Tensor("int64", BigInt64Array.from(values.map(BigInt)), [1, ids.length]);
    const output = await accent.run({
      input_ids: integers(ids),
      attention_mask: integers(ids.map(() => 1)),
      token_type_ids: integers(ids.map(() => 0)),
    });
    const logits = Array.from(output.logits.data);
    learnStress(word, JSON.stringify(ids.map((_, i) => logits.slice(3 * i, 3 * i + 3))));
  }
}

async function resolveHomographs(sentence) {
  const { text, queries } = JSON.parse(homographQueries(sentence));
  if (!queries.length) return accentuateSentence(text, "[]");
  const chosen = [];
  for (const [start, end, candidates, sequences] of queries) {
    let bestIndex = 0;
    let bestScore = -Infinity;
    for (let i = 0; i < sequences.length; i++) {
      const ids = sequences[i];
      const integers = (values) => new ort.Tensor("int64", BigInt64Array.from(values.map(BigInt)), [1, ids.length]);
      const output = await omograph.run({
        input_ids: integers(ids),
        attention_mask: integers(ids.map(() => 1)),
      });
      const logits = output.logits.data;
      const score = logits[1] - logits[0];
      if (score > bestScore) { bestScore = score; bestIndex = i; }
    }
    chosen.push([start, end, candidates[bestIndex]]);
  }
  return accentuateSentence(text, JSON.stringify(chosen));
}

// Keep sentence splitting and terminal punctuation in sync with tiny_voca_ru.frontend.tokenize.
const ABBREVIATIONS = /(?:(?<![\p{L}\p{N}_])(?:тыс|млн|млрд|руб|коп|долл|ул|пр|просп|пер|наб|пл|обл|кв|корп|стр|рис|табл|см|ср|напр|проф|акад|доц|им|св|гр|тел|др|т\.е|т\.д|т\.п|т\.к|т\.н)|(?<![\p{L}\p{N}_])[A-ZА-ЯЁ])\.$/iu;

function sentences(text) {
  const parts = [];
  let current = "";
  for (const piece of text.replace(/\s+/g, " ").trim().split(/(?<=[.!?…]["')\]»“]?)\s+(?=["'(\[«„]?[A-ZА-ЯЁ0-9])/u)) {
    current = current ? current + " " + piece : piece;
    if (!ABBREVIATIONS.test(current)) { parts.push(current); current = ""; }
  }
  if (current) parts.push(current);
  return parts.filter((part) => /[\p{L}\p{N}]/u.test(part));
}

function finished(text) {
  const body = text.replace(/\s+$/, "");
  if (!body) return text;
  const last = body.split(/\s+/).pop();
  const marked = /[\p{L}\p{N}]/u.test(last) ? /[.!?…,;:]["'”’)\]»]*$/.test(last) : /[.!?…]/.test(last) || /^[,;:]$/.test(last);
  return marked ? text : body + ".";
}

function fit(sentence) {
  const result = JSON.parse(phonemize(sentence, true));
  if (!result) return [];
  if (result.ids.length <= config.model.max_tokens - 12) return [result];
  const pieces = sentence.split(/(?<=[,;:—])\s+/);
  if (pieces.length > 1) return pieces.flatMap(fit);
  const words = sentence.split(" ");
  if (words.length < 2) return [];
  const half = Math.ceil(words.length / 2);
  return [...fit(words.slice(0, half).join(" ")), ...fit(words.slice(half).join(" "))];
}

async function synthesize(phones, voice, speed) {
  const count = phones.ids.length;
  const voiceId = Array.isArray(config.voices) ? config.voices.indexOf(voice) : -1;
  if (voiceId < 0) throw new Error("Unsupported Tiny Voca voice.");
  const integers = (values) => new ort.Tensor("int64", BigInt64Array.from(values.map(BigInt)), [1, count]);
  const scalar = (value) => new ort.Tensor("float32", Float32Array.from([value]), [1]);
  const output = await session.run({
    tokens: integers(phones.ids),
    stress: integers(phones.stress),
    voice: new ort.Tensor("int64", BigInt64Array.from([BigInt(voiceId)]), [1]),
    speed: scalar(speed),
    pitch_shift: scalar(0),
    pitch_range: scalar(1),
  });
  return output.audio.data;
}

const PEAK = 0.99;
function limitPeak(samples) {
  let loudest = 0;
  for (let i = 0; i < samples.length; i++) loudest = Math.max(loudest, Math.abs(samples[i]));
  if (loudest > PEAK) for (let i = 0; i < samples.length; i++) samples[i] *= PEAK / loudest;
}

const RUSSIAN_ONLY = "tiny-voca-ru пока читает только по-русски.";

async function speak({ id, text, voice, speed }) {
  const started = performance.now();
  const list = sentences(finished(text));
  const chunks = [];
  let phonemeTime = 0;
  let modelTime = 0;
  for (let index = 0; index < list.length; index++) {
    let mark = performance.now();
    const stressed = await resolveHomographs(list[index]);
    const utterances = fit(stressed);
    phonemeTime += performance.now() - mark;
    for (const phones of utterances) {
      mark = performance.now();
      const audio = await synthesize(phones, voice, speed);
      modelTime += performance.now() - mark;
      chunks.push(audio);
      const heard = audio.slice();
      limitPeak(heard);
      postMessage(
        {
          type: "sentence",
          id,
          index,
          total: list.length,
          phonemes: phones.text,
          audio: heard,
          sampleRate: config.sample_rate,
        },
        [heard.buffer],
      );
    }
  }
  if (!chunks.length) {
    postMessage({ type: "error", id, message: RUSSIAN_ONLY, plain: true });
    return;
  }
  const length = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
  const audio = new Float32Array(length);
  let offset = 0;
  for (const chunk of chunks) { audio.set(chunk, offset); offset += chunk.length; }
  limitPeak(audio);
  postMessage(
    {
      type: "done",
      id,
      audio,
      sampleRate: config.sample_rate,
      phonemeTime,
      modelTime,
      totalTime: performance.now() - started,
    },
    [audio.buffer],
  );
}

onmessage = async ({ data }) => {
  try {
    if (data.type === "load") {
      downloads.clear();
      await Promise.all([loadPhonemizer(), loadModel(), loadAccent(), loadOmograph()]);
      postMessage({ type: "ready", voices: config.voices, sampleRate: config.sample_rate });
    } else if (data.type === "speak") {
      await stressUnknownWords(data.text);
      await speak(data);
    }
  } catch (error) {
    postMessage({ type: "error", id: data.id, message: String((error && error.message) || error) });
  }
};
