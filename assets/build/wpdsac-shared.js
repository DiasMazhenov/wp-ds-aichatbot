/**
 * WP DS AI Chatbot – Shared utilities.
 *
 * Loaded before chat.js. All symbols are exposed on window.wpdsacShared
 * so that chat.js can reference them without re-defining locally.
 *
 * @package WPDSAIChatbot
 */
(() => {
	'use strict';

	const leadNavigationHash = '#wpdsac-contact-form';

	const getConfig = () => window.wpdsacChatConfig || {};

	// ── REST request helper ───────────────────────────────

	const request = async (path, body = {}) => {
		const config = getConfig();
		const strings = config.strings || {};
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

	// ── Scroll helper ─────────────────────────────────────

	const scrollToLatest = (chat, behavior = 'auto') => {
		const messages = chat.querySelector('.wpdsac-chat__messages');
		if (!messages) {
			return;
		}

		window.requestAnimationFrame(() => {
			messages.scrollTo({top: messages.scrollHeight, behavior});
		});
	};

	// ── Markdown rendering ────────────────────────────────

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

	const renderMarkdown = (container, text) => {
		const lines = text.split('\n');
		let i = 0;

		while (i < lines.length) {
			const line = lines[i];

			if (line.startsWith('```')) {
				const codeLines = [];
				i++;
				while (i < lines.length && !lines[i].startsWith('```')) {
					codeLines.push(lines[i]);
					i++;
				}
				i++;
				const pre = document.createElement('pre');
				pre.className = 'wpdsac-chat__code';
				const code = document.createElement('code');
				code.textContent = codeLines.join('\n');
				pre.appendChild(code);
				container.appendChild(pre);
				continue;
			}

			if (/^(-{3,}|\*{3,}|_{3,})\s*$/.test(line)) {
				container.appendChild(document.createElement('hr'));
				i++;
				continue;
			}

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

			if (line.startsWith('> ')) {
				const bq = document.createElement('blockquote');
				bq.className = 'wpdsac-chat__blockquote';
				applyInlineMarkdown(bq, line.slice(2));
				container.appendChild(bq);
				i++;
				continue;
			}

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

			if (line.trim() === '') {
				i++;
				continue;
			}

			const p = document.createElement('p');
			p.className = 'wpdsac-chat__md-paragraph';
			applyInlineMarkdown(p, line);
			container.appendChild(p);
			i++;
		}
	};

	// ── Navigation URL helper ─────────────────────────────

	const safeNavigationUrl = (value) => {
		try {
			const url = new URL(value, window.location.href);
			return url.origin === window.location.origin && ['http:', 'https:'].includes(url.protocol) ? url : null;
		} catch (error) {
			return null;
		}
	};

	// ── Assistant content rendering ───────────────────────

	const assistantPreviewText = (message) => message
		.replace(/\[\[WPDSAC_(?:NAV|ACTION|QA)\|[^\]]+\]\]/giu, '')
		.replace(/\[([^\]]+)\]\([^)]+\)/gu, '$1')
		.replace(/(^|\s)[#>*_`]+/gu, '$1')
		.replace(/[*_`]+/gu, '')
		.trim();

	const appendAssistantContent = (container, message) => {
		const marker = /\[\[WPDSAC_(NAV|ACTION)\|([^|\]]+)\|([^\]]+)\]\]/giu;
		let offset = 0;
		let match;
		const strings = getConfig().strings || {};

		while ((match = marker.exec(message)) !== null) {
			renderMarkdown(container, message.slice(offset, match.index));
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

		renderMarkdown(container, message.slice(offset));
	};

	const animateAssistantContent = async (chat, messages, item, message) => {
		const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		const enabled = chat.dataset.wpdsacMessageAnimation !== '0';
		const preview = assistantPreviewText(message);
		const words = preview.match(/\S+\s*/gu) || [];

		if (!enabled || reduceMotion || words.length < 2) {
			appendAssistantContent(item, message);
			return;
		}

		const configuredDelay = Math.min(250, Math.max(20, Number.parseInt(chat.dataset.wpdsacMessageWordDelay || '70', 10)));
		const delay = Math.max(20, Math.min(configuredDelay, Math.floor(12000 / words.length)));
		messages.setAttribute('aria-busy', 'true');
		item.classList.add('is-typing');
		item.setAttribute('aria-label', preview);

		for (const word of words) {
			const span = document.createElement('span');
			span.className = 'wpdsac-chat__typing-word';
			span.setAttribute('aria-hidden', 'true');
			span.textContent = word;
			item.appendChild(span);
			scrollToLatest(chat);
			await new Promise((resolve) => window.setTimeout(resolve, delay));
		}

		item.textContent = '';
		item.classList.remove('is-typing');
		item.removeAttribute('aria-label');
		appendAssistantContent(item, message);
		messages.setAttribute('aria-busy', 'false');
		scrollToLatest(chat);
	};

	// ── Message append ────────────────────────────────────

	const appendMessage = (chat, message, role, animate = false) => {
		const messages = chat.querySelector('.wpdsac-chat__messages');
		const row = document.createElement('div');
		const isBot = role === 'bot';
		const item = document.createElement(isBot ? 'div' : 'p');
		row.className = `wpdsac-chat__message-row wpdsac-chat__message-row--${role}`;
		item.className = `wpdsac-chat__message wpdsac-chat__message--${role}`;
		item.dataset.wpdsacMessageContent = message;
		if (isBot) {
			const avatarUrl = chat.dataset.wpdsacAvatarUrl || '';
			if (avatarUrl) {
				const avatarFrame = document.createElement('span');
				const avatar = document.createElement('img');
				avatarFrame.className = 'wpdsac-chat__avatar-frame';
				avatarFrame.setAttribute('aria-hidden', 'true');
				avatar.className = 'wpdsac-chat__avatar';
				avatar.src = avatarUrl;
				avatar.alt = '';
				avatar.width = 32;
				avatar.height = 32;
				avatar.decoding = 'async';
				avatar.style.objectPosition = `${chat.dataset.wpdsacAvatarPositionX || 50}% ${chat.dataset.wpdsacAvatarPositionY || 50}%`;
				avatar.style.transform = `scale(${chat.dataset.wpdsacAvatarScale || 1})`;
				avatarFrame.appendChild(avatar);
				row.appendChild(avatarFrame);
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
			if (!animate) {
				appendAssistantContent(item, message);
			}
		} else {
			item.textContent = message;
		}
		row.appendChild(item);
		messages.appendChild(row);
		scrollToLatest(chat);

		return isBot && animate
			? animateAssistantContent(chat, messages, item, message)
			: Promise.resolve();
	};

	// ── Expose on window ──────────────────────────────────

	window.wpdsacShared = {
		leadNavigationHash,
		request,
		scrollToLatest,
		renderMarkdown,
		safeNavigationUrl,
		assistantPreviewText,
		appendAssistantContent,
		animateAssistantContent,
		appendMessage,
	};
})();
