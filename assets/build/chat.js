(() => {
	'use strict';

	const config = window.wpdsacChatConfig || {};
	const strings = config.strings || {};
	const sessionStorageKey = 'wpdsacSessionToken';

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

	const appendMessage = (chat, message, role) => {
		const messages = chat.querySelector('.wpdsac-chat__messages');
		const item = document.createElement('p');
		item.className = `wpdsac-chat__message wpdsac-chat__message--${role}`;
		item.textContent = message;
		messages.appendChild(item);
	};

	document.addEventListener('click', (event) => {
		const toggle = event.target.closest('[data-wpdsac-chat] .wpdsac-chat__toggle');
		if (!toggle) {
			return;
		}

		const chat = toggle.closest('[data-wpdsac-chat]');
		const panel = chat.querySelector('.wpdsac-chat__panel');
		const expanded = toggle.getAttribute('aria-expanded') === 'true';

		toggle.setAttribute('aria-expanded', String(!expanded));
		panel.hidden = expanded;
		chat.classList.toggle('is-expanded', !expanded);
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

		button.disabled = true;
		status.textContent = strings.connecting || '';

		try {
			const session = await getSessionToken();
			status.textContent = strings.sending || '';
			const response = await request('/chat', {session, message});

			appendMessage(chat, message, 'user');
			appendMessage(chat, response.reply, 'bot');
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
		const lead = form.closest('[data-wpdsac-lead]');
		const status = lead.querySelector('[data-wpdsac-lead-status]');
		const name = form.elements.name.value.trim();
		const email = form.elements.email.value.trim();
		const consent = form.elements.consent.checked;
		const website = form.elements.website.value.trim();

		if (!email || !consent || button.disabled) {
			form.reportValidity();
			return;
		}

		button.disabled = true;
		status.textContent = strings.leadSaving || '';

		try {
			const session = await getSessionToken();
			const response = await request('/lead', {
				session,
				name,
				email,
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
