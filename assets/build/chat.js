(() => {
	'use strict';

	const config = window.wpdsacChatConfig || {};
	const strings = config.strings || {};
	const sessionStorageKey = 'wpdsacSessionToken';
	const visitorNameStorageKey = 'wpdsacVisitorName';
	const conversationHistoryStorageKey = 'wpdsacConversationHistory';
	const leadNavigationHash = '#wpdsac-contact-form';
	const conversationLifetime = 24 * 60 * 60 * 1000;
	let audioContext = null;
	const modalReturnFocus = new WeakMap();
	const leadModalByChat = new WeakMap();
	const chatByLeadModal = new WeakMap();
	const userMessageCounters = new WeakMap();
	const leadAutoTriggered = new WeakMap();
	const leadState = new WeakMap();

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

	const renderMarkdown = (container, text) => {
		const lines = text.split('\n');
		let i = 0;

		while (i < lines.length) {
			const line = lines[i];

			// Code block (```...```)
			if (line.startsWith('```')) {
				const codeLines = [];
				i++;
				while (i < lines.length && !lines[i].startsWith('```')) {
					codeLines.push(lines[i]);
					i++;
				}
				i++; // skip closing ```
				const pre = document.createElement('pre');
				pre.className = 'wpdsac-chat__code';
				const code = document.createElement('code');
				code.textContent = codeLines.join('\n');
				pre.appendChild(code);
				container.appendChild(pre);
				continue;
			}

			// Horizontal rule
			if (/^(-{3,}|\*{3,}|_{3,})\s*$/.test(line)) {
				container.appendChild(document.createElement('hr'));
				i++;
				continue;
			}

			// Heading
			const headingMatch = line.match(/^(#{1,6})\s+(.*)/);
			if (headingMatch) {
				const level = headingMatch[1].length;
				const tag = `h${Math.min(level, 6)}`;
				const heading = document.createElement(tag);
				applyInlineMarkdown(heading, headingMatch[2]);
				container.appendChild(heading);
				i++;
				continue;
			}

			// Blockquote
			if (line.startsWith('> ')) {
				const bq = document.createElement('blockquote');
				bq.className = 'wpdsac-chat__blockquote';
				applyInlineMarkdown(bq, line.slice(2));
				container.appendChild(bq);
				i++;
				continue;
			}

			// Unordered list
			const ulMatch = line.match(/^[\s]*[-*+]\s+(.*)/);
			if (ulMatch) {
				const ul = document.createElement('ul');
				while (i < lines.length) {
					const lm = lines[i].match(/^[\s]*[-*+]\s+(.*)/);
					if (!lm) break;
					const li = document.createElement('li');
					applyInlineMarkdown(li, lm[1]);
					ul.appendChild(li);
					i++;
				}
				container.appendChild(ul);
				continue;
			}

			// Ordered list
			const olMatch = line.match(/^[\s]*\d+\.\s+(.*)/);
			if (olMatch) {
				const ol = document.createElement('ol');
				while (i < lines.length) {
					const lm = lines[i].match(/^[\s]*\d+\.\s+(.*)/);
					if (!lm) break;
					const li = document.createElement('li');
					applyInlineMarkdown(li, lm[1]);
					ol.appendChild(li);
					i++;
				}
				container.appendChild(ol);
				continue;
			}

			// Empty line
			if (line.trim() === '') {
				i++;
				continue;
			}

			// Paragraph
			const p = document.createElement('p');
			p.className = 'wpdsac-chat__md-paragraph';
			applyInlineMarkdown(p, line);
			container.appendChild(p);
			i++;
		}
	};

	const applyInlineMarkdown = (el, text) => {
		const regex = /\*\*(.+?)\*\*|__(.+?)__|_(.+?)_|\*(.+?)\*|`([^`]+)`|\[([^\]]+)\]\(([^)]+)\)/g;
		let lastIndex = 0;
		let match;

		while ((match = regex.exec(text)) !== null) {
			if (match.index > lastIndex) {
				el.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));
			}

			if (match[1] || match[2]) {
				const strong = document.createElement('strong');
				strong.textContent = match[1] || match[2];
				el.appendChild(strong);
			} else if (match[3]) {
				const em = document.createElement('em');
				em.textContent = match[3];
				el.appendChild(em);
			} else if (match[4]) {
				const em = document.createElement('em');
				em.textContent = match[4];
				el.appendChild(em);
			} else if (match[5]) {
				const code = document.createElement('code');
				code.className = 'wpdsac-chat__inline-code';
				code.textContent = match[5];
				el.appendChild(code);
			} else if (match[6] && match[7]) {
				const linkText = match[6];
				const href = match[7];
				const isUrl = /^https?:\/\//i.test(href);
				if (isUrl) {
					const a = document.createElement('a');
					a.href = href;
					a.textContent = linkText;
					a.target = '_blank';
					a.rel = 'noopener noreferrer';
					el.appendChild(a);
				} else {
					el.appendChild(document.createTextNode(`[${linkText}](${href})`));
				}
			}

			lastIndex = regex.lastIndex;
		}

		if (lastIndex < text.length) {
			el.appendChild(document.createTextNode(text.slice(lastIndex)));
		}
	};

	const safeNavigationUrl = (value) => {
		try {
			const url = new URL(value, window.location.href);
			return url.origin === window.location.origin && ['http:', 'https:'].includes(url.protocol) ? url : null;
		} catch (error) {
			return null;
		}
	};

	const appendAssistantContent = (container, message) => {
		const marker = /\[\[WPDSAC_(NAV|ACTION|QA)\|([^|\]]+)\|([^|\]]+)(?:\|([^\]]+))?\]\]/giu;
		let offset = 0;
		let match;

		while ((match = marker.exec(message)) !== null) {
			renderMarkdown(container, message.slice(offset, match.index));
			const markerType = match[1].toUpperCase();
			const markerValue = match[2].trim();
			const label = match[3].trim().slice(0, 120);
			const extra = (match[4] || '').trim().slice(0, 500);
			const url = markerType === 'NAV' ? safeNavigationUrl(markerValue) : null;
			const isLeadAction = (markerType === 'ACTION' && markerValue === 'lead_form')
				|| (url && url.hash === leadNavigationHash);

			if (markerType === 'QA' && label && extra) {
				const qaBtn = document.createElement('button');
				qaBtn.type = 'button';
				qaBtn.className = 'wpdsac-chat__quick-action wpdsac-chat__qa-action';
				qaBtn.textContent = label;
				if (markerValue === 'url') {
					qaBtn.dataset.wpdsacQaUrl = extra;
				} else {
					qaBtn.dataset.wpdsacQaMessage = extra;
				}
				container.appendChild(qaBtn);
			} else if (label && (url || isLeadAction)) {
				const action = document.createElement('button');
				action.type = 'button';
				action.className = 'wpdsac-chat__navigation-action';
				if (isLeadAction) {
					action.dataset.wpdsacAction = 'lead-form';
				} else {
					action.dataset.wpdsacNavigationUrl = url.href;
					action.dataset.wpdsacNavigationLabel = label;
				}
				action.textContent = isLeadAction ? label : `${strings.navigate || 'Go to'}: ${label}`;
				container.appendChild(action);
			}

			offset = marker.lastIndex;
		}

		renderMarkdown(container, message.slice(offset));
	};

	const scrollToLatest = (chat, behavior = 'auto') => {
		const messages = chat.querySelector('.wpdsac-chat__messages');
		if (!messages) {
			return;
		}

		window.requestAnimationFrame(() => {
			messages.scrollTo({top: messages.scrollHeight, behavior});
		});
	};

	const appendMessage = (chat, message, role) => {
		const messages = chat.querySelector('.wpdsac-chat__messages');
		if (role === 'bot') {
			messages.querySelectorAll('.wpdsac-chat__qa-action').forEach(function(b) { b.remove(); });
		}
		const row = document.createElement('div');
		const isBot = role === 'bot';
		const item = document.createElement(isBot ? 'div' : 'p');
		row.className = `wpdsac-chat__message-row wpdsac-chat__message-row--${role}`;
		item.className = `wpdsac-chat__message wpdsac-chat__message--${role}`;
		if (isBot) {
			const avatarUrl = chat.dataset.wpdsacAvatarUrl || '';
			if (avatarUrl) {
				const avatar = document.createElement('img');
				avatar.className = 'wpdsac-chat__avatar';
				avatar.src = avatarUrl;
				avatar.alt = '';
				avatar.width = 32;
				avatar.height = 32;
				avatar.decoding = 'async';
				row.appendChild(avatar);
			} else {
				const avatar = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
				avatar.setAttribute('class', 'wpdsac-chat__avatar');
				avatar.setAttribute('viewBox', '0 0 24 24');
				avatar.setAttribute('width', '20');
				avatar.setAttribute('height', '20');
				avatar.setAttribute('aria-hidden', 'true');
				avatar.innerHTML = '<path fill="currentColor" d="M12 2.75c.47 4.88 4.37 8.78 9.25 9.25-4.88.47-8.78 4.37-9.25 9.25C11.53 16.37 7.63 12.47 2.75 12 7.63 11.53 11.53 7.63 12 2.75Z"/>';
				row.appendChild(avatar);
			}
			appendAssistantContent(item, message);
		} else {
			item.textContent = message;
		}
		row.appendChild(item);
		messages.appendChild(row);
		scrollToLatest(chat);
	};

	const getVisitorName = () => window.sessionStorage.getItem(visitorNameStorageKey) || '';
	const formatVisitorTemplate = (template, name = '') => template
		.split('{username}').join(name)
		.split('(username)').join(name)
		.replace(/\s+([!?,.:;])/gu, '$1')
		.replace(/[ \t]{2,}/g, ' ')
		.trim();

	const extractLeadDetails = (message) => {
		const phoneMatch = message.match(/(?:\+?\d[\d\s().\/-]{5,}\d)/u);
		const phone = phoneMatch ? phoneMatch[0].trim() : '';
		const digitCount = phone.replace(/\D/g, '').length;
		const namePatterns = [
			/(?:меня\s+зовут|мо[её]\s+имя|имя)\s*[:—-]?\s*([\p{L}][\p{L}'’-]*(?:\s+[\p{L}][\p{L}'’-]*){0,2}?)(?=\s*(?:[,.;]|мой|телефон|номер|\+?\d|$))/iu,
			/(?:my\s+name\s+is|name)\s*[:—-]?\s*([\p{L}][\p{L}'’-]*(?:\s+[\p{L}][\p{L}'’-]*){0,2}?)(?=\s*(?:[,.;]|my|phone|number|\+?\d|$))/iu,
		];
		let name = '';

		for (const pattern of namePatterns) {
			const match = message.match(pattern);
			if (match) {
				name = match[1].trim();
				break;
			}
		}

		if (!name && phone && message.trim().startsWith(phone) === false) {
			const beforePhone = message.slice(0, message.indexOf(phone))
				.replace(/\b(?:оставляю|оставить|контакты?|телефон|номер|мой|мои|перезвоните|позвоните|свяжитесь|phone|call|contact|me|my|is)\b/giu, ' ')
				.replace(/[^\p{L}'’\-\s]/gu, ' ')
				.replace(/\s+/g, ' ')
				.trim();

			if (/^[\p{L}][\p{L}'’-]*(?:\s+[\p{L}][\p{L}'’-]*){0,2}$/u.test(beforePhone)) {
				name = beforePhone;
			}
		}

		return {
			name: name.slice(0, 100),
			phone: digitCount >= 7 && digitCount <= 20 ? phone.slice(0, 50) : '',
		};
	};

	const getConversationHistory = (chat) => {
		const entries = Array.from(chat.querySelectorAll('.wpdsac-chat__messages .wpdsac-chat__message'));
		const history = [];
		let remainingCharacters = 20000;

		for (let index = entries.length - 1; index >= 0 && history.length < 30 && remainingCharacters > 0; index -= 1) {
			const entry = entries[index];
			const content = entry.textContent.trim().slice(0, Math.min(4000, remainingCharacters));

			if (!content) {
				continue;
			}

			history.unshift({
				role: entry.classList.contains('wpdsac-chat__message--user') ? 'user' : 'assistant',
				content,
			});
			remainingCharacters -= content.length;
		}

		return history;
	};

	const collectNavigationTargets = () => {
		const targets = [];
		const seen = new Set();
		const addTarget = (label, urlValue) => {
			const url = safeNavigationUrl(urlValue);
			label = String(label || '').replace(/\s+/g, ' ').trim().slice(0, 120);
			if (!url || !label || seen.has(url.href) || targets.length >= 40) {
				return;
			}

			seen.add(url.href);
			targets.push({label, url: url.href});
		};
		const leadAction = document.querySelector('[data-wpdsac-open-lead]');
		if (leadAction) {
			const leadUrl = new URL(window.location.href);
			leadUrl.hash = leadNavigationHash;
			addTarget(leadAction.textContent || strings.leaveRequest || 'Оставить заявку', leadUrl.href);
		}

		document.querySelectorAll('main [id], article [id], .elementor [id]').forEach((element) => {
			if (element.closest('[data-wpdsac-chat]') || !element.id) {
				return;
			}

			const heading = element.matches('h1,h2,h3,h4,h5,h6') ? element : element.querySelector('h1,h2,h3,h4,h5,h6');
			const label = element.getAttribute('aria-label') || heading?.textContent || '';
			const url = new URL(window.location.href);
			url.hash = element.id;
			addTarget(label, url.href);
		});

		document.querySelectorAll('a[href]').forEach((link) => {
			if (link.closest('[data-wpdsac-chat]')) {
				return;
			}
			addTarget(link.textContent, link.href);
		});

		return targets;
	};

	const getStoredConversation = () => {
		const fallback = {startedAt: Date.now(), entries: [], expired: false};

		try {
			const stored = JSON.parse(window.sessionStorage.getItem(conversationHistoryStorageKey) || 'null');

			if (Array.isArray(stored)) {
				return {startedAt: Date.now(), entries: stored, expired: false};
			}

			if (!stored || !Array.isArray(stored.entries) || !Number.isFinite(stored.startedAt)) {
				return fallback;
			}

			if (Date.now() - stored.startedAt >= conversationLifetime) {
				window.sessionStorage.removeItem(conversationHistoryStorageKey);
				return {...fallback, expired: true};
			}

			return stored;
		} catch (error) {
			return fallback;
		}
	};

	const persistConversationHistory = (chat) => {
		try {
			const stored = getStoredConversation();
			window.sessionStorage.setItem(
				conversationHistoryStorageKey,
				JSON.stringify({
					startedAt: stored.startedAt,
					entries: getConversationHistory(chat),
				})
			);
		} catch (error) {
			// The current page conversation still works when browser storage is unavailable.
		}
	};

	const restoreConversationHistory = (chat) => {
		const stored = getStoredConversation();
		const history = stored.entries;

		if (stored.expired) {
			window.sessionStorage.removeItem(sessionStorageKey);
		}

		if (!Array.isArray(history) || history.length === 0) {
			return;
		}

		const messages = chat.querySelector('.wpdsac-chat__messages');
		messages.textContent = '';

		history.slice(-30).forEach((entry) => {
			if (!entry || !['user', 'assistant'].includes(entry.role) || typeof entry.content !== 'string') {
				return;
			}

			appendMessage(chat, entry.content.slice(0, 4000), entry.role === 'assistant' ? 'bot' : 'user');
		});
	};

	const ensureConversationFresh = (chat) => {
		if (!getStoredConversation().expired) {
			return;
		}

		const name = getVisitorName();
		const welcome = formatVisitorTemplate(chat.dataset.wpdsacWelcomeMessage || '', name);
		const messages = chat.querySelector('.wpdsac-chat__messages');
		window.sessionStorage.removeItem(sessionStorageKey);
		messages.textContent = '';
		appendMessage(chat, welcome, 'bot');
	};

	const revealConversation = (chat, name) => {
		const conversation = chat.querySelector('[data-wpdsac-conversation]');
		const leadName = chat.querySelector('[data-wpdsac-lead-form] [name="name"]');
		const intro = chat.querySelector('[data-wpdsac-intro-bubble]');

		if (conversation) {
			conversation.hidden = false;
		}
		chat.querySelectorAll('[data-wpdsac-message-template]').forEach((message) => {
			message.textContent = formatVisitorTemplate(message.dataset.wpdsacMessageTemplate, name);
		});
		if (intro) {
			intro.textContent = formatVisitorTemplate(chat.dataset.wpdsacWelcomeMessage || '', name);
		}
		if (leadName && name) {
			leadName.value = name;
		}
	};

	const showIntroBubble = (chat) => {
		const intro = chat.querySelector('[data-wpdsac-intro-bubble]');
		const expanded = chat.querySelector('.wpdsac-chat__toggle')?.getAttribute('aria-expanded') === 'true';

		if (intro && !expanded && getStoredConversation().entries.length <= 1) {
			intro.hidden = false;
		}
	};

	const scheduleIntroBubble = (chat) => {
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
			revealConversation(chat, getVisitorName());
			scrollToLatest(chat);
			chat.querySelector('[data-wpdsac-form] input')?.focus();
		}
	};

	const prepareLeadModal = (chat) => {
		const lead = chat.querySelector('[data-wpdsac-lead]');
		if (!lead) {
			return;
		}

		leadModalByChat.set(chat, lead);
		chatByLeadModal.set(lead, chat);
		lead.classList.add('wpdsac-chat');
		document.body.appendChild(lead);
	};

	const openLeadForm = (chat, details = {}) => {
		const lead = leadModalByChat.get(chat) || chat.querySelector('[data-wpdsac-lead]');
		if (!lead) {
			return;
		}

		if (lead.hidden) {
			appendMessage(chat, lead.dataset.wpdsacLeadPrompt || '', 'bot');
			persistConversationHistory(chat);
		}

		modalReturnFocus.set(lead, document.activeElement);
		lead.hidden = false;
		document.body.classList.add('wpdsac-modal-open');
		const form = lead.querySelector('[data-wpdsac-lead-form]');
		if (details.name) {
			form.elements.name.value = details.name;
			window.sessionStorage.setItem(visitorNameStorageKey, details.name);
		}
		if (details.phone) {
			form.elements.phone.value = details.phone;
		}
		if (details.request && !form.elements.request.value) {
			form.elements.request.value = details.request;
		}
		window.requestAnimationFrame(() => lead.querySelector('input:not([type="hidden"])')?.focus());
	};

	const closeLeadForm = (lead) => {
		if (!lead || lead.hidden) {
			return;
		}

		lead.hidden = true;
		document.body.classList.remove('wpdsac-modal-open');
		const returnFocus = modalReturnFocus.get(lead);
		if (returnFocus instanceof HTMLElement) {
			returnFocus.focus();
		}
	};

	document.querySelectorAll('[data-wpdsac-chat]').forEach((chat) => {
		prepareLeadModal(chat);
		restoreConversationHistory(chat);
		revealConversation(chat, getVisitorName());
		scheduleIntroBubble(chat);
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

	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Enter' && event.key !== ' ') {
			return;
		}

		const toggle = event.target.closest('[data-wpdsac-chat] .wpdsac-chat__toggle');
		if (!toggle || toggle !== event.target) {
			return;
		}

		event.preventDefault();
		toggle.click();
	});

	document.addEventListener('click', (event) => {
		const action = event.target.closest('[data-wpdsac-quick-action]');
		if (!action) {
			return;
		}

		if (action.matches('[data-wpdsac-open-lead]')) {
			openLeadForm(action.closest('[data-wpdsac-chat]'));
			return;
		}

		if (action.dataset.wpdsacQuickMessage) {
			const chat = action.closest('[data-wpdsac-chat]');
			const form = chat.querySelector('[data-wpdsac-form]');
			form.querySelector('input').value = action.dataset.wpdsacQuickMessage;
			form.requestSubmit();
		}
	});

	document.addEventListener('click', (event) => {
		const close = event.target.closest('[data-wpdsac-close-lead]');
		if (close) {
			closeLeadForm(close.closest('[data-wpdsac-lead]'));
		}
	});

	document.addEventListener('click', (event) => {
		const action = event.target.closest('[data-wpdsac-action="lead-form"]');
		if (!action) {
			return;
		}

		const chat = action.closest('[data-wpdsac-chat]');
		if (chat) {
			openLeadForm(chat);
		}
	});

	document.addEventListener('click', (event) => {
		const action = event.target.closest('[data-wpdsac-navigation-url]');
		if (!action) {
			return;
		}

		const url = safeNavigationUrl(action.dataset.wpdsacNavigationUrl || '');
		if (!url) {
			return;
		}

		const chat = action.closest('[data-wpdsac-chat]');
		const label = action.dataset.wpdsacNavigationLabel || action.textContent || '';
		const isLeadAction = url.hash === leadNavigationHash
			|| /(?:оставить\s+заявк|заявк|связаться|leave\s+(?:a\s+)?request|contact)/iu.test(label);
		if (isLeadAction && chat) {
			openLeadForm(chat);
			return;
		}

		const samePage = url.pathname === window.location.pathname && url.search === window.location.search;
		let target = null;
		if (samePage && url.hash) {
			try {
				target = document.getElementById(decodeURIComponent(url.hash.slice(1)));
			} catch (error) {
				target = null;
			}
		}
		if (target) {
			setExpanded(chat, false);
			target.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start'});
			target.classList.add('wpdsac-navigation-highlight');
			window.setTimeout(() => target.classList.remove('wpdsac-navigation-highlight'), 2200);
			return;
		}

		window.location.assign(url.href);
	});

	document.addEventListener('click', (event) => {
		const action = event.target.closest('[data-wpdsac-qa-message], [data-wpdsac-qa-url]');
		if (!action) {
			return;
		}

		const chat = action.closest('[data-wpdsac-chat]');
		const form = chat?.querySelector('[data-wpdsac-form]');

		if (action.dataset.wpdsacQaMessage && form) {
			form.querySelector('input').value = action.dataset.wpdsacQaMessage;
			form.requestSubmit();
			return;
		}

		const url = safeNavigationUrl(action.dataset.wpdsacQaUrl || '');
		if (url) {
			window.open(url.href, '_blank', 'noopener');
		}
	});

	document.addEventListener('keydown', (event) => {
		const lead = document.querySelector('[data-wpdsac-lead]:not([hidden])');
		if (!lead) {
			return;
		}

		if (event.key === 'Escape') {
			event.preventDefault();
			closeLeadForm(lead);
			return;
		}

		if (event.key !== 'Tab') {
			return;
		}

		const focusable = Array.from(lead.querySelectorAll('button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), a[href]'));
		if (!focusable.length) {
			return;
		}

		const first = focusable[0];
		const last = focusable[focusable.length - 1];
		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
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
		const status = chat.querySelector('[data-wpdsac-status]');
		const message = input.value.trim();

		if (!message || button.disabled) {
			return;
		}

		ensureAudioContext();

		button.disabled = true;

		const ls = leadState.get(chat);

		if (ls && ls.phase !== 'idle') {
			appendMessage(chat, message, 'user');
			input.value = '';

			if (ls.phase === 'waiting_name') {
				const details = extractLeadDetails(message);
				const name = details.name || message.split(/[,;\n]/)[0]?.trim().slice(0, 100) || '';
				const phone = details.phone || '';

				if (!name || name.length < 2) {
					appendMessage(chat, strings.leadNameInvalid || 'Пожалуйста, укажите ваше имя.', 'bot');
					button.disabled = false;
					persistConversationHistory(chat);
					return;
				}

				if (phone && phone.replace(/\D/g, '').length >= 7) {
					leadState.set(chat, { phase: 'idle', name: '' });
					window.sessionStorage.setItem(visitorNameStorageKey, name.slice(0, 100));
					status.textContent = strings.leadSaving || '';
					try {
						const session = await getSessionToken();
						await request('/lead', {
							session,
							name,
							phone,
							consent: true,
							request: '',
							transcript: getConversationHistory(chat).map((e) => `${e.role === 'assistant' ? 'Bot' : 'User'}: ${e.content}`).join('\n'),
						});
						appendMessage(chat, strings.leadSaved || 'Спасибо! Мы свяжемся с вами в ближайшее время.', 'bot');
						playReplySound(chat.dataset.wpdsacReplySound || 'off');
						status.textContent = '';
						const leadWrap = chat.querySelector('[data-wpdsac-lead]');
						if (leadWrap) leadWrap.hidden = true;
						const quickLead = chat.querySelector('[data-wpdsac-quick-action="lead"]');
						if (quickLead) quickLead.hidden = true;
					} catch (error) {
						status.textContent = error.message || strings.leadError || 'Не удалось сохранить заявку.';
					} finally {
						button.disabled = false;
					}
					persistConversationHistory(chat);
					return;
				}

				ls.name = name;
				ls.phase = 'waiting_phone';
				window.sessionStorage.setItem(visitorNameStorageKey, name.slice(0, 100));
				appendMessage(chat, strings.leadAskPhone || 'Отлично! Теперь укажите номер телефона для связи.', 'bot');
				playReplySound(chat.dataset.wpdsacReplySound || 'off');
				button.disabled = false;
				persistConversationHistory(chat);
				return;
			}

			if (ls.phase === 'waiting_phone') {
				const phone = extractLeadDetails(message).phone || message.replace(/[^0-9+().\/\-\s]/g, '').trim();
				if (!phone || phone.replace(/\D/g, '').length < 7) {
					appendMessage(chat, strings.leadPhoneInvalid || 'Некорректный номер. Укажите телефон, например: 8 900 123-45-67', 'bot');
					button.disabled = false;
					persistConversationHistory(chat);
					return;
				}

				leadState.set(chat, { phase: 'idle', name: '' });
				status.textContent = strings.leadSaving || '';

				try {
					const session = await getSessionToken();
					await request('/lead', {
						session,
						name: ls.name,
						phone,
						consent: true,
						request: '',
						transcript: getConversationHistory(chat).map((e) => `${e.role === 'assistant' ? 'Bot' : 'User'}: ${e.content}`).join('\n'),
					});
					appendMessage(chat, strings.leadSaved || 'Спасибо! Мы свяжемся с вами в ближайшее время.', 'bot');
					playReplySound(chat.dataset.wpdsacReplySound || 'off');
					status.textContent = '';
					const leadWrap2 = chat.querySelector('[data-wpdsac-lead]');
					if (leadWrap2) leadWrap2.hidden = true;
					const quickLead2 = chat.querySelector('[data-wpdsac-quick-action="lead"]');
					if (quickLead2) quickLead2.hidden = true;
				} catch (error) {
					status.textContent = error.message || strings.leadError || 'Не удалось сохранить заявку.';
				} finally {
					button.disabled = false;
				}
				persistConversationHistory(chat);
				return;
			}
		}

		const leadDetails = extractLeadDetails(message);
		const contactDigits = leadDetails.phone.replace(/\D/g, '').length;
		const hasContactIntent = /\b(оставить\s+(?:заявк[а-яё]*|контакт[а-яё]*)|хочу\s+оставить\s+(?:номер|телефон)|связаться|перезвон[а-яё]*|позвон[а-яё]*|contact\s+me|leave\s+(?:my\s+contacts?|a\s+request)|call\s+me)\b/iu.test(message);

		if (hasContactIntent || (leadDetails.phone && (leadDetails.name || contactDigits >= 10))) {
			appendMessage(chat, message, 'user');
			input.value = '';
			button.disabled = false;
			const qa = chat.querySelector('[data-wpdsac-quick-action="lead"]');
			if (qa) qa.hidden = true;
			openLeadForm(chat, {...leadDetails, request: message});
			persistConversationHistory(chat);
			return;
		}

		status.textContent = strings.connecting || '';

		try {
			ensureConversationFresh(chat);
			const session = await getSessionToken();
			status.textContent = strings.sending || '';
				const response = await request('/chat', {
					session,
					message,
					visitor_name: getVisitorName(),
					history: getConversationHistory(chat),
					navigation_targets: collectNavigationTargets(),
				});

			appendMessage(chat, message, 'user');
			appendMessage(chat, response.reply, 'bot');
			persistConversationHistory(chat);
			playReplySound(chat.dataset.wpdsacReplySound || 'off');
			input.value = '';
			status.textContent = '';

			const count = (userMessageCounters.get(chat) || 0) + 1;
			userMessageCounters.set(chat, count);

			if (count >= 5 && !leadAutoTriggered.get(chat)) {
				leadAutoTriggered.set(chat, true);
				const qb = chat.querySelector('[data-wpdsac-quick-action="lead"]');
				if (qb) qb.hidden = true;
				const leadEl = chat.querySelector('[data-wpdsac-lead]');
				const leadPrompt = leadEl?.dataset?.wpdsacLeadPrompt || '';
				setTimeout(() => {
					appendMessage(chat, leadPrompt || 'Пожалуйста, оставьте ваше имя и телефон для связи.', 'bot');
					leadState.set(chat, { phase: 'waiting_name', name: '' });
				}, 600);
			}
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

		const lead = form.closest('[data-wpdsac-lead]');
		const chat = lead ? chatByLeadModal.get(lead) : null;
		const button = form.querySelector('button[type="submit"]');
		if (!lead || !chat || !button) {
			console.error('[WP DS AI Chatbot] Lead form is detached from its chat instance.');
			return;
		}

		const status = lead.querySelector('[data-wpdsac-lead-status]');
		const name = form.elements.name.value.trim();
		const phone = form.elements.phone.value.trim();
		const leadRequest = form.elements.request.value.trim();
		const consent = form.elements.consent.checked;
		const website = form.elements.website.value.trim();

		if (!name || !phone || !consent || button.disabled) {
			status.textContent = !name || !phone ? (strings.leadPhoneRequired || '') : '';
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
				phone,
				request: leadRequest,
				transcript,
				consent,
				website,
			});

			closeLeadForm(lead);
			window.sessionStorage.setItem(visitorNameStorageKey, name.slice(0, 100));
			appendMessage(chat, response.message || '', 'bot');
			persistConversationHistory(chat);
			playReplySound(chat.dataset.wpdsacReplySound || 'off');
			form.reset();
			status.textContent = '';
			if (response.notified === false) {
				console.warn('[WP DS AI Chatbot] Lead saved, but WordPress could not send the notification email. Configure SMTP and verify the notification address.');
			}
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
