'use strict';

const assert = require('node:assert/strict');
const test = require('node:test');
const { createVoiceController } = require('../../assets/build/wpdsac-voice.js');

const createHarness = (initialStorage = {}) => {
	const listeners = {};
	const storage = new Map(Object.entries(initialStorage));
	const label = { textContent: '' };
	const button = {
		attributes: {},
		setAttribute(name, value) {
			this.attributes[name] = value;
		},
		querySelector() {
			return label;
		},
	};
	const status = { textContent: '', hidden: true };
	const chat = {
		dataset: {},
		querySelector() {
			return { textContent: 'Welcome, visitor!' };
		},
	};
	const document = {
		querySelectorAll(selector) {
			if (selector === '[data-wpdsac-voice]') return [button];
			if (selector === '[data-wpdsac-voice-status]') return [status];
			return [];
		},
		addEventListener(type, callback) {
			listeners[type] = callback;
		},
	};
	const audioSources = [];
	const audioContext = {
		state: 'running',
		currentTime: 10,
		destination: {},
		createBuffer(_channels, length, sampleRate) {
			return {
				duration: length / sampleRate,
				copyToChannel(audio) {
					this.audio = audio;
				},
			};
		},
		createBufferSource() {
			const source = {
				connect() {},
				start(time) {
					this.startTime = time;
				},
				stop() {
					this.stopped = true;
				},
			};
			audioSources.push(source);
			return source;
		},
	};
	const workers = [];
	class FakeWorker {
		constructor(url) {
			this.url = url;
			this.messages = [];
			this.terminated = false;
			workers.push(this);
		}
		postMessage(message) {
			this.messages.push(message);
		}
		terminate() {
			this.terminated = true;
		}
		emit(data) {
			this.onmessage({ data });
		}
	}
	const controller = createVoiceController({
		config: { voiceWorkerUrl: '/assets/tiny-voca-worker.js' },
		strings: {
			voiceEnabled: 'Speech is on',
			voiceDisabled: 'Speech is off',
			voiceEnable: 'Turn on speech',
			voiceDisable: 'Turn off speech',
			voiceLoading: 'Loading voice model',
			voiceError: 'Voice unavailable',
		},
		document,
		storage: {
			getItem(key) {
				return storage.get(key) || null;
			},
			setItem(key, value) {
				storage.set(key, value);
			},
		},
		Worker: FakeWorker,
		getAudioContext: () => audioContext,
		cleanText: (text) => text,
	});

	const click = (onVoiceButton = false) => {
		const target = {
			closest(selector) {
				if (selector === '[data-wpdsac-chat]') return chat;
				if (selector === '[data-wpdsac-voice]') return onVoiceButton ? button : null;
				return null;
			},
		};
		listeners.click({ target });
	};

	return { audioSources, button, chat, click, controller, label, status, storage, workers };
};

test('voice starts by default, speaks the welcome, and plays worker sentence audio', () => {
	const harness = createHarness();
	assert.equal(harness.button.attributes['aria-pressed'], 'true');
	assert.equal(harness.label.textContent, 'Speech is on');

	harness.click();
	assert.equal(harness.workers.length, 1);
	assert.deepEqual(harness.workers[0].messages[0], { type: 'load' });
	harness.workers[0].emit({ type: 'ready' });
	assert.equal(harness.workers[0].messages[1].text, 'Welcome, visitor!');
	assert.equal(harness.workers[0].messages[1].voice, 'F');

	harness.workers[0].emit({
		type: 'sentence',
		id: harness.workers[0].messages[1].id,
		audio: new Float32Array([0.1, 0.2]),
		sampleRate: 22050,
	});
	assert.equal(harness.audioSources.length, 1);
	assert.equal(harness.audioSources[0].startTime, 10.02);
});

test('mute stops audio, persists preference, and unmute starts the model again', () => {
	const harness = createHarness();
	harness.click();
	harness.workers[0].emit({ type: 'ready' });
	const message = harness.workers[0].messages.find((entry) => entry.type === 'speak');
	harness.workers[0].emit({
		type: 'sentence',
		id: message.id,
		audio: new Float32Array([0.1, 0.2]),
		sampleRate: 22050,
	});

	harness.click(true);
	assert.equal(harness.button.attributes['aria-pressed'], 'false');
	assert.equal(harness.label.textContent, 'Speech is off');
	assert.equal(harness.storage.get('wpdsacVoiceMuted'), '1');
	assert.equal(harness.audioSources[0].stopped, true);
	assert.equal(harness.workers[0].terminated, true);

	harness.click(true);
	assert.equal(harness.button.attributes['aria-pressed'], 'true');
	assert.equal(harness.storage.get('wpdsacVoiceMuted'), '0');
	assert.equal(harness.workers.length, 2);
	harness.workers[1].emit({ type: 'ready' });
	harness.controller.speak('A new reply.');
	assert.equal(harness.workers[1].messages.at(-1).text, 'A new reply.');
});

test('collapsing the chat stops current speech and discards queued speech', () => {
	const harness = createHarness();
	harness.click();
	const worker = harness.workers[0];
	worker.emit({ type: 'ready' });
	const message = worker.messages.find((entry) => entry.type === 'speak');
	worker.emit({
		type: 'sentence',
		id: message.id,
		audio: new Float32Array([0.1, 0.2]),
		sampleRate: 22050,
	});
	harness.controller.speak('This queued message must not be played.');

	harness.controller.collapse();
	assert.equal(harness.audioSources[0].stopped, true);
	worker.emit({
		type: 'sentence',
		id: message.id,
		audio: new Float32Array([0.3, 0.4]),
		sampleRate: 22050,
	});
	worker.emit({ type: 'done', id: message.id });

	assert.equal(harness.audioSources.length, 1);
	assert.equal(worker.messages.filter((entry) => entry.type === 'speak').length, 1);
});

test('a previously saved mute preference is applied before the visitor interacts', () => {
	const harness = createHarness({ wpdsacVoiceMuted: '1' });
	assert.equal(harness.button.attributes['aria-pressed'], 'false');
	assert.equal(harness.button.attributes['aria-label'], 'Turn on speech');
	assert.equal(harness.label.textContent, 'Speech is off');

	harness.click();
	assert.equal(harness.workers.length, 0);
});
