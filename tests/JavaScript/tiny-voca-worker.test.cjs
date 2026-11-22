'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const workerPath = path.join(__dirname, '../../assets/build/tiny-voca-worker.js');
const modelBase = 'https://huggingface.co/spaces/egnch/tiny-voca-ru-demo/resolve/bf78c265c4cd02a7853d1bcf1d8bcc1fa72aa69e/';

test('bundled worker passes MIME checks, pins model assets, and maps female voice F to speaker ID zero', async () => {
	const importedScripts = [];
	const fetchedUrls = [];
	const workerGlobal = {
		URL,
		ort: {
			env: { wasm: {} },
			Tensor: class Tensor {
				constructor(type, data, dimensions) {
					this.type = type;
					this.data = data;
					this.dimensions = dimensions;
				}
			},
		},
		postMessage() {},
		fetch: async (resource) => {
			fetchedUrls.push(String(resource));
			return { ok: true, status: 200 };
		},
		importScripts: (...urls) => {
			urls.forEach((url) => {
				const contentType = url.includes('tiny-voca-ru-demo') ? 'text/plain' : 'text/javascript';
				assert.match(contentType, /javascript/, 'importScripts must receive a JavaScript MIME type');
				importedScripts.push(url);
			});
		},
	};
	workerGlobal.self = workerGlobal;

	const context = vm.createContext(workerGlobal);
	vm.runInContext(fs.readFileSync(workerPath, 'utf8'), context, { filename: workerPath });

	assert.equal(typeof workerGlobal.onmessage, 'function');
	assert.equal(importedScripts.length, 2);
	assert.ok(importedScripts.every((url) => url.startsWith('https://cdn.jsdelivr.net/')));

	await workerGlobal.fetch('assets/config.json?v=ru-3');
	assert.equal(fetchedUrls[0], new URL('assets/config.json?v=ru-3', modelBase).href);

	vm.runInContext(`
		config = { voices: ['F', 'M'] };
		session = {
			run: async (inputs) => {
				globalThis.synthesizedVoice = inputs.voice;
				return { audio: { data: new Float32Array([0.25]) } };
			},
		};
	`, context);
	const audio = await vm.runInContext(`synthesize({ ids: [1], stress: [0] }, 'F', 1)`, context);
	assert.equal(workerGlobal.synthesizedVoice.data[0], 0n);
	assert.deepEqual(Array.from(audio), [0.25]);
});
