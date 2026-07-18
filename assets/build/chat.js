(() => {
	'use strict';

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

	document.addEventListener('submit', (event) => {
		const form = event.target.closest('[data-wpdsac-form]');
		if (!form) {
			return;
		}

		event.preventDefault();
		const status = form.parentElement.querySelector('[data-wpdsac-status]');
		status.textContent = form.dataset.unavailableMessage || '';
	});
})();
