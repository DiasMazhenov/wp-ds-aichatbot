(() => {
	'use strict';

	const config = window.wpdsacChatConfig || {};
	const strings = config.strings || {};
	const sessionStorageKey = 'wpdsacSessionToken';
	const visitorNameStorageKey = 'wpdsacVisitorName';
	let audioContext = null;

	const ensureAudioContext = () => {
		const AudioContext = window.AudioContext || window.webkitAudioContext;
		if (!AudioContext) {
			return null;
		}

		if (!audioContext) {
			audioContext = new AudioContext();
		}
		if (audioContext.state === 'suspended') {
			audioContext.resume().catch(() => {});
		}

		return audioContext;
	};

	const playTone = (context, frequency, delay, duration, volume, wave = 'sine', endFrequency = frequency) => {
		const start = context.currentTime + delay;
		const end = start + duration;
		const oscillator = context.createOscillator();
		const gain = context.createGain();

		oscillator.type = wave;
		oscillator.frequency.setValueAtTime(frequency, start);
		oscillator.frequency.exponentialRampToValueAtTime(endFrequency, end);
		gain.gain.setValueAtTime(0.0001, start);
		gain.gain.exponentialRampToValueAtTime(volume, start + 0.015);
		gain.gain.exponentialRampToValueAtTime(0.0001, end);
		oscillator.connect(gain);
		gain.connect(context.destination);
		oscillator.start(start);
		oscillator.stop(end);
	};

	const playReplySound = (sound) => {
		if (!sound || sound === 'off') {
			return;
		}

		const context = ensureAudioContext();
		if (!context) {
			return;
		}

		if (sound === 'chime') {
			playTone(context, 660, 0, 0.11, 0.018);
			playTone(context, 880, 0.07, 0.14, 0.015);
		} else if (sound === 'pop') {
			playTone(context, 420, 0, 0.1, 0.02, 'triangle', 680);
		} else {
			playTone(context, 620, 0, 0.12, 0.025);
		}
	};

	const request = async (path, body = {}) => {
		const headers = {
			'Content-Type': 'application/json',
		};

		if (config.restNonce) {
			headers['X-WP-Nonce'] = config.restNonce;
		}

		const response = await fetch(`${config.restUrl}${path}`, {
			method: 'POST',
			credentials: 'same-origin',
			headers,
			body: JSON.stringify(body),
		});
		const data = await response.json().catch(() => ({}));

		if (!response.ok) {
			const error = new Error(data.message || strings.error || 'Request failed.');
			error.status = response.status;
			error.code = data.code || 'wpdsac_unknown_error';
			console.error('[WP DS AI Chatbot] REST request failed', {
				path,
				status: response.status,
				code: error.code,
				message: error.message,
			});
			throw error;
		}

		return data;
	};

	const getSessionToken = async () => {
		let token = window.sessionStorage.getItem(sessionStorageKey);
		if (token) {
			return token;
		}

		const session = await request('/session');
		token = session.token;
		window.sessionStorage.setItem(sessionStorageKey, token);

		return token;
	};

	const appendLinkedText = (container, message) => {
		const parts = message.split(/(https?:\/\/[^\s]+)/g);

		parts.forEach((part) => {
			if (!part.startsWith('http://') && !part.startsWith('https://')) {
				container.appendChild(document.createTextNode(part));
				return;
			}

			const urlText = part.replace(/[),.!?;:]+$/u, '');
			const trailing = part.slice(urlText.length);

			try {
				const url = new URL(urlText);
				if (url.protocol !== 'http:' && url.protocol !== 'https:') {
					throw new Error('Unsupported link protocol.');
				}

				const link = document.createElement('a');
				link.href = url.href;
				link.textContent = urlText;
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				container.appendChild(link);
				container.appendChild(document.createTextNode(trailing));
			} catch (error) {
				container.appendChild(document.createTextNode(part));
			}
		});
	};

	const appendMessage = (chat, message, role) => {
		const messages = chat.querySelector('.wpdsac-chat__messages');
		const row = document.createElement('div');
		const item = document.createElement('p');
		row.className = `wpdsac-chat__message-row wpdsac-chat__message-row--${role}`;
		item.className = `wpdsac-chat__message wpdsac-chat__message--${role}`;
		if (role === 'bot') {
			const avatar = document.createElement('img');
			avatar.className = 'wpdsac-chat__avatar';
			avatar.src = chat.dataset.wpdsacAvatarUrl || '';
			avatar.alt = '';
			avatar.width = 32;
			avatar.height = 32;
			avatar.decoding = 'async';
			row.appendChild(avatar);
			appendLinkedText(item, message);
		} else {
			item.textContent = message;
		}
		row.appendChild(item);
		messages.appendChild(row);
		messages.scrollTop = messages.scrollHeight;
	};

	const getVisitorName = () => window.sessionStorage.getItem(visitorNameStorageKey) || '';

	const revealConversation = (chat, name) => {
		const gate = chat.querySelector('[data-wpdsac-name-gate]');
		const conversation = chat.querySelector('[data-wpdsac-conversation]');
		const leadName = chat.querySelector('[data-wpdsac-lead-form] [name="name"]');
		const intro = chat.querySelector('[data-wpdsac-intro-bubble]');

		if (gate) {
			gate.hidden = true;
		}
		if (conversation) {
			conversation.hidden = false;
		}
		chat.querySelectorAll('[data-wpdsac-message-template]').forEach((message) => {
			message.textContent = message.dataset.wpdsacMessageTemplate
				.split('{username}').join(name)
				.split('(username)').join(name);
		});
		if (leadName && name) {
			leadName.value = name;
		}
		if (intro) {
			intro.hidden = true;
		}
	};

	const showIntroBubble = (chat) => {
		const intro = chat.querySelector('[data-wpdsac-intro-bubble]');
		const expanded = chat.querySelector('.wpdsac-chat__toggle')?.getAttribute('aria-expanded') === 'true';

		if (intro && !expanded && !getVisitorName()) {
			intro.hidden = false;
		}
	};

	const scheduleIntroBubble = (chat) => {
		if (getVisitorName()) {
			return;
		}

		const trigger = chat.dataset.wpdsacIntroTrigger || 'delay';
		const delay = Math.max(0, Math.min(300, Number.parseInt(chat.dataset.wpdsacIntroDelay || '10', 10))) * 1000;

		if (trigger === 'disabled') {
			return;
		}
		if (trigger === 'immediate') {
			showIntroBubble(chat);
			return;
		}
		if (trigger === 'scroll') {
			const onScroll = () => {
				const pageHeight = Math.max(document.documentElement.scrollHeight, document.body.scrollHeight);
				if (pageHeight > 0 && (window.scrollY + window.innerHeight) / pageHeight >= 0.5) {
					window.removeEventListener('scroll', onScroll);
					showIntroBubble(chat);
				}
			};
			window.addEventListener('scroll', onScroll, {passive: true});
			onScroll();
			return;
		}
		if (trigger === 'exit' && !window.matchMedia('(pointer: coarse)').matches) {
			const onExit = (event) => {
				if (event.clientY > 0) {
					return;
				}
				document.removeEventListener('mouseout', onExit);
				showIntroBubble(chat);
			};
			document.addEventListener('mouseout', onExit);
			return;
		}

		window.setTimeout(() => showIntroBubble(chat), delay);
	};

	const setExpanded = (chat, expanded) => {
		const toggle = chat.querySelector('.wpdsac-chat__toggle');
		const panel = chat.querySelector('.wpdsac-chat__panel');
		toggle.setAttribute('aria-expanded', String(expanded));
		panel.hidden = !expanded;
		chat.classList.toggle('is-expanded', expanded);

		if (expanded) {
			const intro = chat.querySelector('[data-wpdsac-intro-bubble]');
			if (intro) {
				intro.hidden = true;
			}
			const name = getVisitorName();
			if (name) {
				revealConversation(chat, name);
				chat.querySelector('[data-wpdsac-form] input')?.focus();
			} else {
				chat.querySelector('[data-wpdsac-name-form] input')?.focus();
			}
		}
	};

	document.querySelectorAll('[data-wpdsac-chat]').forEach((chat) => {
		const name = getVisitorName();
		if (name) {
			revealConversation(chat, name);
		} else {
			scheduleIntroBubble(chat);
		}
	});

	document.addEventListener('click', (event) => {
		const toggle = event.target.closest('[data-wpdsac-chat] .wpdsac-chat__toggle, [data-wpdsac-intro-bubble]');
		if (!toggle) {
			return;
		}

		const chat = toggle.closest('[data-wpdsac-chat]');
		ensureAudioContext();
		const headerToggle = chat.querySelector('.wpdsac-chat__toggle');
		const expanded = headerToggle.getAttribute('aria-expanded') === 'true';
		setExpanded(chat, toggle.matches('[data-wpdsac-intro-bubble]') ? true : !expanded);
	});

	document.addEventListener('submit', (event) => {
		const form = event.target.closest('[data-wpdsac-name-form]');
		if (!form) {
			return;
		}

		event.preventDefault();
		const name = form.elements.visitor_name.value.trim();
		if (!name) {
			form.reportValidity();
			return;
		}

		window.sessionStorage.setItem(visitorNameStorageKey, name.slice(0, 100));
		const chat = form.closest('[data-wpdsac-chat]');
		revealConversation(chat, name);
		chat.querySelector('[data-wpdsac-form] input')?.focus();
	});

	document.addEventListener('click', (event) => {
		const button = event.target.closest('[data-wpdsac-open-lead]');
		if (!button) {
			return;
		}

		const chat = button.closest('[data-wpdsac-chat]');
		const lead = chat.querySelector('[data-wpdsac-lead]');
		lead.hidden = false;
		lead.querySelector('input:not([type="hidden"])')?.focus();
		lead.scrollIntoView({behavior: 'smooth', block: 'nearest'});
	});

	document.addEventListener('submit', async (event) => {
		const form = event.target.closest('[data-wpdsac-form]');
		if (!form) {
			return;
		}

		event.preventDefault();

		const chat = form.closest('[data-wpdsac-chat]');
		const input = form.querySelector('input');
		const button = form.querySelector('button[type="submit"]');
		const status = form.parentElement.querySelector('[data-wpdsac-status]');
		const message = input.value.trim();

		if (!message || button.disabled) {
			return;
		}

		ensureAudioContext();

		button.disabled = true;

		if (/\b(оставить\s+заявк[а-яё]*|связаться|перезвон[а-яё]*|contact\s+me|leave\s+(a\s+)?request|call\s+me)\b/iu.test(message)) {
			appendMessage(chat, message, 'user');
			input.value = '';
			button.disabled = false;
			chat.querySelector('[data-wpdsac-open-lead]')?.click();
			return;
		}

		status.textContent = strings.connecting || '';

		try {
			const session = await getSessionToken();
			status.textContent = strings.sending || '';
			const response = await request('/chat', {
				session,
				message,
				visitor_name: getVisitorName(),
			});

			appendMessage(chat, message, 'user');
			appendMessage(chat, response.reply, 'bot');
			playReplySound(chat.dataset.wpdsacReplySound || 'off');
			input.value = '';
			status.textContent = '';
		} catch (error) {
			if (error.status === 401) {
				window.sessionStorage.removeItem(sessionStorageKey);
			}
			status.textContent = error.message || strings.error || '';
		} finally {
			button.disabled = false;
		}
	});

	document.addEventListener('submit', async (event) => {
		const form = event.target.closest('[data-wpdsac-lead-form]');
		if (!form) {
			return;
		}

		event.preventDefault();

		const button = form.querySelector('button[type="submit"]');
		const chat = form.closest('[data-wpdsac-chat]');
		const lead = form.closest('[data-wpdsac-lead]');
		const status = lead.querySelector('[data-wpdsac-lead-status]');
		const name = form.elements.name.value.trim();
		const email = form.elements.email.value.trim();
		const phone = form.elements.phone.value.trim();
		const leadRequest = form.elements.request.value.trim();
		const consent = form.elements.consent.checked;
		const website = form.elements.website.value.trim();

		if (!email || !consent || button.disabled) {
			status.textContent = !email ? (strings.leadEmailRequired || '') : '';
			form.reportValidity();
			return;
		}

		button.disabled = true;
		status.textContent = strings.leadSaving || '';

		try {
			const session = await getSessionToken();
			const transcript = Array.from(chat.querySelectorAll('.wpdsac-chat__message'))
				.map((item) => `${item.classList.contains('wpdsac-chat__message--user') ? 'Посетитель' : 'Ассистент'}: ${item.textContent.trim()}`)
				.join('\n\n')
				.slice(0, 20000);
			const response = await request('/lead', {
				session,
				name,
				email,
				phone,
				request: leadRequest,
				transcript,
				consent,
				website,
			});

			form.hidden = true;
			status.textContent = response.message || '';
		} catch (error) {
			if (error.status === 401) {
				window.sessionStorage.removeItem(sessionStorageKey);
			}
			status.textContent = error.message || strings.leadError || '';
		} finally {
			button.disabled = false;
		}
	});
})();
