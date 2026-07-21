(() => {
	'use strict';

	const config = window.wpdsacChatConfig || {};
	const strings = config.strings || {};
	const sessionStorageKey = 'wpdsacSessionToken';
	const visitorNameStorageKey = 'wpdsacVisitorName';
	const conversationHistoryStorageKey = 'wpdsacConversationHistory';
	const hiddenQuickActionsStorageKey = 'wpdsacHiddenQuickActions';
	const leadNavigationHash = '#wpdsac-contact-form';
	const conversationLifetime = 24 * 60 * 60 * 1000;
	let audioContext = null;
	const modalReturnFocus = new WeakMap();
	const leadModalByChat = new WeakMap();
	const chatByLeadModal = new WeakMap();

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
				db_message: data.data?.db_message || '',
				table_exists: data.data?.table_exists,
				db_version: data.data?.db_version,
				sql_fragment: data.data?.sql_fragment || '',
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

	const safeNavigationUrl = (value) => {
		try {
			const url = new URL(value, window.location.href);
			return url.origin === window.location.origin && ['http:', 'https:'].includes(url.protocol) ? url : null;
		} catch (error) {
			return null;
		}
	};

	const appendAssistantContent = (container, message) => {
		const marker = /\[\[WPDSAC_(NAV|ACTION)\|([^|\]]+)\|([^\]]+)\]\]/giu;
		let offset = 0;
		let match;

		while ((match = marker.exec(message)) !== null) {
			appendLinkedText(container, message.slice(offset, match.index));
			const markerType = match[1].toUpperCase();
			const markerValue = match[2].trim();
			const label = match[3].trim().slice(0, 120);
			const url = markerType === 'NAV' ? safeNavigationUrl(markerValue) : null;
			const isLeadAction = (markerType === 'ACTION' && markerValue === 'lead_form')
				|| (url && url.hash === leadNavigationHash);

			if (label && (url || isLeadAction)) {
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

		appendLinkedText(container, message.slice(offset));
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
			window.sessionStorage.removeItem(hiddenQuickActionsStorageKey);
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
		window.sessionStorage.removeItem(hiddenQuickActionsStorageKey);
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

	const getHiddenQuickActions = () => {
		try {
			const actions = JSON.parse(window.sessionStorage.getItem(hiddenQuickActionsStorageKey) || '[]');
			return Array.isArray(actions) ? actions : [];
		} catch (error) {
			return [];
		}
	};

	const hideQuickAction = (action) => {
		if (!action) {
			return;
		}

		action.hidden = true;
		const actionId = action.dataset.wpdsacQuickAction;
		const hiddenActions = Array.from(new Set([...getHiddenQuickActions(), actionId]));

		try {
			window.sessionStorage.setItem(hiddenQuickActionsStorageKey, JSON.stringify(hiddenActions));
		} catch (error) {
			// The action remains hidden on the current page.
		}

		const container = action.closest('[data-wpdsac-quick-actions]');
		if (container && !container.querySelector('[data-wpdsac-quick-action]:not([hidden])')) {
			container.hidden = true;
		}
	};

	const restoreQuickActions = (chat) => {
		const hiddenActions = getHiddenQuickActions();
		chat.querySelectorAll('[data-wpdsac-quick-action]').forEach((action) => {
			if (hiddenActions.includes(action.dataset.wpdsacQuickAction)) {
				action.hidden = true;
			}
		});

		const container = chat.querySelector('[data-wpdsac-quick-actions]');
		if (container) {
			container.hidden = !container.querySelector('[data-wpdsac-quick-action]:not([hidden])');
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
		lead.style.cssText = chat.style.cssText;
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
		restoreQuickActions(chat);
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

		hideQuickAction(action);

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
		const leadDetails = extractLeadDetails(message);
		const contactDigits = leadDetails.phone.replace(/\D/g, '').length;
		const hasContactIntent = /\b(оставить\s+(?:заявк[а-яё]*|контакт[а-яё]*)|хочу\s+оставить\s+(?:номер|телефон)|связаться|перезвон[а-яё]*|позвон[а-яё]*|contact\s+me|leave\s+(?:my\s+contacts?|a\s+request)|call\s+me)\b/iu.test(message);

		if (hasContactIntent || (leadDetails.phone && (leadDetails.name || contactDigits >= 10))) {
			appendMessage(chat, message, 'user');
			input.value = '';
			button.disabled = false;
			hideQuickAction(chat.querySelector('[data-wpdsac-quick-action="lead"]'));
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
