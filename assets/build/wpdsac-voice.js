/* Browser-side playback controller for Tiny Voca RU. */
(() => {
	'use strict';

	const createVoiceController = (options = {}) => {
		const config = options.config || {};
		const strings = options.strings || {};
		const windowRef = options.window || (typeof window !== 'undefined' ? window : {});
		const documentRef = options.document || (typeof document !== 'undefined' ? document : null);
		const WorkerClass = options.Worker || (typeof Worker !== 'undefined' ? Worker : null);
		const getAudioContext = options.getAudioContext || (() => null);
		const cleanText = options.cleanText || ((text) => text);
		const storageKey = 'wpdsacVoiceMuted';
		let storage = options.storage || null;
		let muted = false;
		let unlocked = false;
		let worker = null;
		let ready = false;
		let loading = false;
		let busy = false;
		let failed = false;
		let sequence = 0;
		let epoch = 0;
		let activeJob = null;
		let scheduledUntil = 0;
		const queue = [];
		const sources = new Set();

		if (!documentRef) {
			return { activate: () => {}, interrupt: () => {}, speak: () => {} };
		}

		if (!storage) {
			try {
				storage = windowRef.localStorage;
			} catch (error) {
				storage = null;
			}
		}

		try {
			muted = Boolean(storage && storage.getItem(storageKey) === '1');
		} catch (error) {
			// Local storage may be disabled by the browser.
		}

		const updateControls = () => {
			documentRef.querySelectorAll('[data-wpdsac-voice]').forEach((button) => {
				button.setAttribute('aria-pressed', String(!muted));
				button.setAttribute('aria-label', muted ? strings.voiceEnable : strings.voiceDisable);
				const label = button.querySelector('[data-wpdsac-voice-label]');
				if (label) {
					label.textContent = muted ? strings.voiceDisabled : strings.voiceEnabled;
				}
			});
		};

		const setStatus = (message) => {
			documentRef.querySelectorAll('[data-wpdsac-voice-status]').forEach((status) => {
				status.textContent = message;
				status.hidden = '' === message;
			});
		};

		const stopPlayback = () => {
			sources.forEach((source) => {
				try {
					source.stop();
				} catch (error) {
					// The audio source may already have ended.
				}
			});
			sources.clear();
			scheduledUntil = 0;
		};

		const pump = () => {
			if (muted || !ready || busy || !queue.length) {
				return;
			}

			const text = queue.shift();
			activeJob = { id: ++sequence, epoch };
			busy = true;

			try {
				worker.postMessage({
					type: 'speak',
					id: activeJob.id,
					text,
					voice: 'F',
					speed: 1,
				});
			} catch (error) {
				busy = false;
				activeJob = null;
				failed = true;
				setStatus(strings.voiceError);
			}
		};

		const handleWorkerMessage = ({ data }) => {
			if (!data || 'object' !== typeof data) {
				return;
			}

			if ('status' === data.type) {
				setStatus(data.text || strings.voiceLoading);
				return;
			}

			if ('ready' === data.type) {
				ready = true;
				loading = false;
				setStatus('');
				pump();
				return;
			}

			if ('error' === data.type && undefined === data.id) {
				failed = true;
				setStatus(strings.voiceError);
				if (worker) {
					worker.terminate();
				}
				worker = null;
				ready = false;
				loading = false;
				busy = false;
				activeJob = null;
				queue.length = 0;
				return;
			}

			if (!activeJob || data.id !== activeJob.id) {
				return;
			}

			if ('sentence' === data.type && activeJob.epoch === epoch && !muted) {
				const context = getAudioContext();
				if (!context || !data.audio || !data.audio.length || !data.sampleRate) {
					return;
				}

				try {
					const buffer = context.createBuffer(1, data.audio.length, data.sampleRate);
					buffer.copyToChannel(data.audio, 0);
					const source = context.createBufferSource();
					source.buffer = buffer;
					source.connect(context.destination);
					sources.add(source);
					source.onended = () => sources.delete(source);
					const startAt = Math.max(context.currentTime + 0.02, scheduledUntil);
					scheduledUntil = startAt + buffer.duration;
					source.start(startAt);
				} catch (error) {
					setStatus(strings.voiceError);
				}
			}

			if ('done' === data.type || 'error' === data.type) {
				const job = activeJob;
				activeJob = null;
				busy = false;
				if ('error' === data.type && job.epoch === epoch && !muted) {
					setStatus(strings.voiceError);
				}
				pump();
			}
		};

		const start = () => {
			if (muted || !unlocked || worker || loading || failed) {
				return;
			}

			if (!config.voiceWorkerUrl || !WorkerClass || !getAudioContext()) {
				failed = true;
				setStatus(strings.voiceError);
				return;
			}

			try {
				loading = true;
				setStatus(strings.voiceLoading);
				worker = new WorkerClass(config.voiceWorkerUrl);
				worker.onmessage = handleWorkerMessage;
				worker.onerror = () => {
					if (worker) {
						worker.terminate();
					}
					worker = null;
					ready = false;
					loading = false;
					busy = false;
					activeJob = null;
					queue.length = 0;
					failed = true;
					setStatus(strings.voiceError);
				};
				worker.postMessage({ type: 'load' });
			} catch (error) {
				worker = null;
				loading = false;
				failed = true;
				setStatus(strings.voiceError);
			}
		};

		const interrupt = () => {
			epoch++;
			queue.length = 0;
			stopPlayback();
		};

		const setMuted = (value) => {
			muted = Boolean(value);
			try {
				if (storage) {
					storage.setItem(storageKey, muted ? '1' : '0');
				}
			} catch (error) {
				// Keep the current preference in memory when storage is unavailable.
			}

			if (muted) {
				interrupt();
				if (worker) {
					worker.terminate();
				}
				worker = null;
				ready = false;
				loading = false;
				busy = false;
				activeJob = null;
				failed = false;
				setStatus('');
			} else {
				unlocked = true;
				start();
			}

			updateControls();
		};

		const speak = (value) => {
			if (muted || !unlocked || 'string' !== typeof value) {
				return;
			}

			const text = cleanText(value)
				.replace(/https?:\/\/\S+/gu, '')
				.replace(/\s+/gu, ' ')
				.trim()
				.slice(0, 5000);
			if (!text) {
				return;
			}

			queue.push(text);
			if (queue.length > 5) {
				queue.shift();
			}
			start();
			pump();
		};

		const playWelcome = (chat) => {
			const greeting = chat.querySelector('[data-wpdsac-message-template]');
			if (greeting && !chat.dataset.wpdsacVoiceWelcomePlayed) {
				chat.dataset.wpdsacVoiceWelcomePlayed = '1';
				speak(greeting.textContent || '');
			}
		};

		const activate = (chat, shouldSpeakWelcome = true) => {
			if (!chat) {
				return;
			}

			unlocked = true;
			const context = getAudioContext();
			if (context && 'suspended' === context.state && 'function' === typeof context.resume) {
				context.resume().catch(() => {});
			}
			start();
			if (shouldSpeakWelcome && !muted) {
				playWelcome(chat);
			}
		};

		const onClick = (event) => {
			const target = event.target;
			if (!target || 'function' !== typeof target.closest) {
				return;
			}

			const chat = target.closest('[data-wpdsac-chat]');
			if (!chat) {
				return;
			}

			const header = target.closest('.wpdsac-chat__toggle');
			if (header && chat.querySelector('.wpdsac-chat__toggle')?.getAttribute('aria-expanded') === 'true') {
				interrupt();
				return;
			}

			if (target.closest('[data-wpdsac-voice]')) {
				setMuted(!muted);
				if (muted) {
					return;
				}
			}

			activate(chat);
		};

		updateControls();
		documentRef.addEventListener('click', onClick);

		return { activate, interrupt, speak, setMuted };
	};

	const api = { createVoiceController };
	if ('undefined' !== typeof module && module.exports) {
		module.exports = api;
	}
	if ('undefined' !== typeof window) {
		window.wpdsacVoice = api;
	}
})();
