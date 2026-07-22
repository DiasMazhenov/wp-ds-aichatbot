(() => {
  'use strict';

  const adminRoot = document.querySelector('.wpdsac-settings-wrap, .wpdsac-admin-page');
  const themeStorageKey = 'wpdsacAdminTheme';

  if (adminRoot) {
    let theme = 'light';

    try {
      theme = window.localStorage.getItem(themeStorageKey)
        || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    } catch (error) {
      theme = 'light';
    }

    const themeButton = document.createElement('button');
    themeButton.type = 'button';
    themeButton.className = 'wpdsac-theme-toggle';
    themeButton.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 3a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V4a1 1 0 0 1 1-1Zm0 5a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm9 3a1 1 0 1 1 0 2h-1a1 1 0 1 1 0-2h1ZM5 11a1 1 0 1 1 0 2H4a1 1 0 1 1 0-2h1Zm13.66-5.07a1 1 0 0 1 0 1.41l-.71.71a1 1 0 0 1-1.41-1.41l.7-.71a1 1 0 0 1 1.42 0ZM7.46 16.54a1 1 0 0 1 0 1.41l-.7.71a1 1 0 0 1-1.42-1.42l.71-.7a1 1 0 0 1 1.41 0Zm10.49 0 .71.7a1 1 0 0 1-1.42 1.42l-.7-.71a1 1 0 0 1 1.41-1.41ZM6.76 5.93l.7.71a1 1 0 0 1-1.41 1.41l-.71-.71a1 1 0 0 1 1.42-1.41ZM13 19v1a1 1 0 1 1-2 0v-1a1 1 0 1 1 2 0Z"/></svg><span></span>';
    const themeLabel = themeButton.querySelector('span');

    const applyTheme = (nextTheme) => {
      theme = nextTheme === 'dark' ? 'dark' : 'light';
      adminRoot.dataset.wpdsacTheme = theme;
      document.body.classList.toggle('wpdsac-admin-theme-dark', theme === 'dark');
      themeButton.setAttribute('aria-pressed', String(theme === 'dark'));
      themeLabel.textContent = theme === 'dark'
        ? window.wpdsacAdmin?.lightTheme || 'Light mode'
        : window.wpdsacAdmin?.darkTheme || 'Dark mode';
    };

    const header = adminRoot.querySelector('.wpdsac-settings-header');
    if (header) {
      header.appendChild(themeButton);
    } else {
      adminRoot.prepend(themeButton);
    }

    applyTheme(theme);

    themeButton.addEventListener('click', () => {
      const nextTheme = theme === 'dark' ? 'light' : 'dark';
      applyTheme(nextTheme);

      try {
        window.localStorage.setItem(themeStorageKey, nextTheme);
      } catch (error) {
        // The selected mode remains active until navigation.
      }
    });
  }

  const settingsWrap = document.querySelector('.wpdsac-settings-wrap');
  const tabs = Array.from(document.querySelectorAll('[data-wpdsac-tab]'));
  const panels = Array.from(document.querySelectorAll('[data-wpdsac-panel]'));
  const storageKey = 'wpdsacActiveSettingsTab';
  let previewAudioContext = null;

  const playSoundPreview = (sound) => {
    if (!sound || sound === 'off') {
      return;
    }

    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) {
      return;
    }

    previewAudioContext = previewAudioContext || new AudioContext();
    if (previewAudioContext.state === 'suspended') {
      previewAudioContext.resume().catch(() => {});
    }

    const tone = (frequency, delay, duration, volume, wave = 'sine', endFrequency = frequency) => {
      const start = previewAudioContext.currentTime + delay;
      const end = start + duration;
      const oscillator = previewAudioContext.createOscillator();
      const gain = previewAudioContext.createGain();
      oscillator.type = wave;
      oscillator.frequency.setValueAtTime(frequency, start);
      oscillator.frequency.exponentialRampToValueAtTime(endFrequency, end);
      gain.gain.setValueAtTime(0.0001, start);
      gain.gain.exponentialRampToValueAtTime(volume, start + 0.015);
      gain.gain.exponentialRampToValueAtTime(0.0001, end);
      oscillator.connect(gain);
      gain.connect(previewAudioContext.destination);
      oscillator.start(start);
      oscillator.stop(end);
    };

    if (sound === 'chime') {
      tone(660, 0, 0.11, 0.018);
      tone(880, 0.07, 0.14, 0.015);
    } else if (sound === 'pop') {
      tone(420, 0, 0.1, 0.02, 'triangle', 680);
    } else {
      tone(620, 0, 0.12, 0.025);
    }
  };

  document.querySelector('[data-wpdsac-sound-preview]')?.addEventListener('click', () => {
    const sound = document.querySelector('[data-wpdsac-sound-select]')?.value || 'off';
    playSoundPreview(sound);
  });

  const activateTab = (tabId, moveFocus = false) => {
    if (!tabs.some((tab) => tab.dataset.wpdsacTab === tabId)) {
      return;
    }

    tabs.forEach((tab) => {
      const active = tab.dataset.wpdsacTab === tabId;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;

      if (active && moveFocus) {
        tab.focus();
      }
    });

    panels.forEach((panel) => {
      panel.classList.toggle('is-active', panel.dataset.wpdsacPanel === tabId);
    });

    try {
      window.sessionStorage.setItem(storageKey, tabId);
    } catch (error) {
      // Settings remain usable when browser storage is unavailable.
    }
  };

  if (settingsWrap && tabs.length && panels.length) {
    settingsWrap.classList.add('is-tabbed');

    let initialTab = window.location.hash.replace('#wpdsac-tab-', '');

    if (!tabs.some((tab) => tab.dataset.wpdsacTab === initialTab)) {
      try {
        initialTab = window.sessionStorage.getItem(storageKey) || 'general';
      } catch (error) {
        initialTab = 'general';
      }
    }

    activateTab(initialTab);

    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => {
        activateTab(tab.dataset.wpdsacTab);
        window.history.replaceState(null, '', `#wpdsac-tab-${tab.dataset.wpdsacTab}`);
      });

      tab.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
          return;
        }

        event.preventDefault();
        let nextIndex = index;

        if (event.key === 'ArrowRight') {
          nextIndex = (index + 1) % tabs.length;
        } else if (event.key === 'ArrowLeft') {
          nextIndex = (index - 1 + tabs.length) % tabs.length;
        } else if (event.key === 'Home') {
          nextIndex = 0;
        } else if (event.key === 'End') {
          nextIndex = tabs.length - 1;
        }

        activateTab(tabs[nextIndex].dataset.wpdsacTab, true);
      });
    });
  }

  const providerSelect = document.querySelector('[data-wpdsac-provider-select]');
  const providerFields = Array.from(document.querySelectorAll('[data-wpdsac-provider-field]'));
  const diagnosticsPanel = document.querySelector('[data-wpdsac-provider-diagnostics]');
  const providerDiagnostics = {...(window.wpdsacAdmin?.providerDiagnostics || {})};

  const renderProviderDiagnostics = (diagnostics) => {
    if (!diagnosticsPanel || !diagnostics) {
      return;
    }

    const values = {
      '[data-wpdsac-debug-provider]': diagnostics.provider || '',
      '[data-wpdsac-debug-model]': diagnostics.model || '—',
      '[data-wpdsac-debug-source]': diagnostics.credentialSource || 'missing',
      '[data-wpdsac-debug-configured]': diagnostics.configured
        ? window.wpdsacAdmin?.configuredYes || 'Yes'
        : window.wpdsacAdmin?.configuredNo || 'No',
    };

    Object.entries(values).forEach(([selector, value]) => {
      const target = diagnosticsPanel.querySelector(selector);

      if (target) {
        target.textContent = value;
      }
    });

    diagnosticsPanel.classList.toggle('is-configured', Boolean(diagnostics.configured));
    diagnosticsPanel.classList.toggle('is-missing', !diagnostics.configured);
  };

  window.wpdsacDebugProvider = () => {
    const provider = providerSelect?.value || '';
    const diagnostics = providerDiagnostics[provider] || {
      provider,
      model: '',
      credentialSource: 'missing',
      configured: false,
    };

    console.info('[WP DS AI Chatbot] Safe provider diagnostics', diagnostics);
    console.info('[WP DS AI Chatbot] Safe provider diagnostics JSON', JSON.stringify(diagnostics));
    return diagnostics;
  };

  const updateProviderFields = () => {
    if (!providerSelect) {
      return;
    }

    providerFields.forEach((field) => {
      const target = field.closest('tr') || field;
      const active = field.dataset.wpdsacProviderField === providerSelect.value;

      if (active) {
        target.hidden = false;
        target.removeAttribute('hidden');
        target.setAttribute('aria-hidden', 'false');
        target.style.removeProperty('display');
        return;
      }

      target.hidden = true;
      target.setAttribute('aria-hidden', 'true');
      target.style.setProperty('display', 'none', 'important');
    });

    renderProviderDiagnostics(providerDiagnostics[providerSelect.value]);
  };

  if (providerSelect) {
    providerSelect.addEventListener('change', updateProviderFields);
    updateProviderFields();
    window.wpdsacDebugProvider();
  }

  const settingsForm = document.querySelector('[data-wpdsac-settings-form]');
  const saveStatus = document.querySelector('[data-wpdsac-save-status]');
  const pendingCredentials = {};

  const providerFromCredentialInput = (input) => {
    const wrapperProvider = input.closest('[data-wpdsac-provider-field]')?.dataset.wpdsacProviderField;
    const optionMatch = input.name?.match(/^wpdsac_([a-z0-9_]+)_api_key$/);

    return input.dataset.wpdsacProvider || wrapperProvider || optionMatch?.[1] || '';
  };

  if (settingsForm && window.wpdsacAdmin) {
    const markUnsaved = () => {
      if (saveStatus) {
        saveStatus.className = 'wpdsac-save-note is-unsaved';
        saveStatus.textContent = window.wpdsacAdmin.unsavedText;
      }
    };

    settingsForm.addEventListener('input', markUnsaved);
    settingsForm.addEventListener('change', markUnsaved);
    settingsForm.addEventListener('input', (event) => {
      const input = event.target.closest?.('input[type="password"]');

      if (!input) {
        return;
      }

      const provider = providerFromCredentialInput(input);

      if (provider) {
        pendingCredentials[provider] = input.value;
      }
    });

    settingsForm.addEventListener('submit', async (event) => {
      event.preventDefault();

      const submitButton = settingsForm.querySelector('[type="submit"]');
      const originalButtonText = submitButton ? submitButton.value : '';
      const formData = new FormData(settingsForm);

      const activeProvider = providerSelect?.value || '';
      const activeCredential = settingsForm.querySelector(
        `[data-wpdsac-provider-field="${activeProvider}"] input[type="password"]`
      ) || settingsForm.querySelector(`[name="wpdsac_${activeProvider}_api_key"]`);
      const capturedCredential = activeCredential?.value || pendingCredentials[activeProvider] || '';
      const credentialPreflight = {
        provider: activeProvider,
        inputFound: Boolean(activeCredential),
        credentialCaptured: Boolean(capturedCredential),
      };

      console.info('[WP DS AI Chatbot] Credential preflight', credentialPreflight);
      console.info('[WP DS AI Chatbot] Credential preflight JSON', JSON.stringify(credentialPreflight));

      if (capturedCredential) {
        formData.set(`wpdsac_credentials[${activeProvider}]`, capturedCredential);
        formData.set(
          'wpdsac_credential_payload',
          JSON.stringify({
            provider: activeProvider,
            credential: capturedCredential,
          })
        );
      }

      formData.set('action', 'wpdsac_save_settings');
      formData.set('nonce', window.wpdsacAdmin.nonce);

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.value = window.wpdsacAdmin.savingText;
      }

      if (saveStatus) {
        saveStatus.className = 'wpdsac-save-note is-saving';
        saveStatus.textContent = window.wpdsacAdmin.savingText;
      }

      try {
        const response = await fetch(window.wpdsacAdmin.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          body: formData,
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
          const saveError = new Error(result?.data?.message || window.wpdsacAdmin.errorText);
          saveError.diagnostics = result?.data?.diagnostics;
          throw saveError;
        }

        if (result.data?.diagnostics) {
          providerDiagnostics[result.data.diagnostics.provider] = result.data.diagnostics;
          renderProviderDiagnostics(result.data.diagnostics);
          console.info('[WP DS AI Chatbot] Settings save diagnostics', result.data.diagnostics);
        }

        delete pendingCredentials[activeProvider];

        document.querySelectorAll('[data-wpdsac-api-key]').forEach((input) => {
          if (!input.value) {
            return;
          }

          input.value = '';
          input.placeholder = window.wpdsacAdmin.savedKeyMask;

          const keyStatus = input.parentElement?.querySelector('[data-wpdsac-key-status]');

          if (keyStatus) {
            keyStatus.hidden = false;
          }
        });

        if (saveStatus) {
          saveStatus.className = 'wpdsac-save-note is-success';
          saveStatus.textContent = result.data?.message || window.wpdsacAdmin.savedText;
        }
      } catch (error) {
        if (error.diagnostics) {
          providerDiagnostics[error.diagnostics.provider] = error.diagnostics;
          renderProviderDiagnostics(error.diagnostics);
        }

        console.error('[WP DS AI Chatbot] Settings save failed', {
          message: error.message || window.wpdsacAdmin.errorText,
          diagnostics: error.diagnostics || window.wpdsacDebugProvider(),
        });
        console.error(
          '[WP DS AI Chatbot] Settings save diagnostics JSON',
          JSON.stringify(error.diagnostics || window.wpdsacDebugProvider())
        );

        if (saveStatus) {
          saveStatus.className = 'wpdsac-save-note is-error';
          saveStatus.textContent = error.message || window.wpdsacAdmin.errorText;
        }
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.value = originalButtonText;
        }
      }
    });
  }

	const avatarControl = document.querySelector('[data-wpdsac-avatar-control]');

	if (avatarControl && window.wp?.media) {
		const avatarId = avatarControl.querySelector('[data-wpdsac-avatar-id]');
		const avatarPreview = avatarControl.querySelector('[data-wpdsac-avatar-preview]');
		const selectAvatar = avatarControl.querySelector('[data-wpdsac-avatar-select]');
		const removeAvatar = avatarControl.querySelector('[data-wpdsac-avatar-remove]');
		let mediaFrame = null;

		const updateAvatar = (id, url, fullUrl) => {
			const hasAvatar = Number.parseInt(id, 10) > 0;
			avatarId.value = id;
			avatarPreview.src = url;
			removeAvatar.hidden = !hasAvatar;
			const cropBtn = document.querySelector('[data-wpdsac-avatar-crop]');
			if (cropBtn) cropBtn.hidden = !hasAvatar;
			const cropImg = document.querySelector('[data-wpdsac-crop-image]');
			if (cropImg && hasAvatar && fullUrl) {
				cropImg.src = fullUrl;
			} else if (cropImg && hasAvatar) {
				cropImg.src = url;
			}
			document.querySelectorAll('[data-wpdsac-admin-avatar]').forEach((image) => {
				image.src = url;
			});
			avatarId.dispatchEvent(new Event('change', {bubbles: true}));
		};

		selectAvatar.addEventListener('click', () => {
			if (!mediaFrame) {
				mediaFrame = window.wp.media({
					title: window.wpdsacAdmin?.chooseAvatar || 'Select chatbot avatar',
					button: {text: window.wpdsacAdmin?.useAvatar || 'Use this avatar'},
					library: {type: 'image'},
					multiple: false,
				});

				mediaFrame.on('select', () => {
					const attachment = mediaFrame.state().get('selection').first()?.toJSON();
					if (!attachment) {
						return;
					}
					const previewUrl = attachment.sizes?.['wpdsac-avatar']?.url || attachment.sizes?.thumbnail?.url || attachment.url;
					const fullUrl = attachment.url;
					updateAvatar(attachment.id, previewUrl, fullUrl);
					const openBtn = document.querySelector('[data-wpdsac-avatar-crop]');
					if (openBtn) openBtn.click();
				});
			}

			mediaFrame.open();
		});

		removeAvatar.addEventListener('click', () => {
			updateAvatar('0', avatarControl.dataset.wpdsacDefaultAvatar);
		});
	}

	const cropModal = document.querySelector('[data-wpdsac-crop-modal]');
	const cropImage = document.querySelector('[data-wpdsac-crop-image]');
	const cropViewport = document.querySelector('[data-wpdsac-crop-viewport]');
	const cropZoom = document.querySelector('[data-wpdsac-crop-zoom]');
	const cropZoomVal = document.querySelector('[data-wpdsac-crop-zoom-value]');
	const cropButton = document.querySelector('[data-wpdsac-avatar-crop]');
	const xInput = document.querySelector('[name="wpdsac_settings[avatar_position_x]"]');
	const yInput = document.querySelector('[name="wpdsac_settings[avatar_position_y]"]');
	const scaleInput = document.querySelector('[name="wpdsac_settings[avatar_scale]"]');
	const avatarPreview = document.querySelector('[data-wpdsac-avatar-preview]');
	const defaultAvatar = document.querySelector('[data-wpdsac-avatar-control]')?.dataset?.wpdsacDefaultAvatar || '';

	let dragState = null;

	if (cropModal && cropImage && cropViewport) {
		const updateCropPreview = () => {
			var x = Number(xInput?.value) || 50;
			var y = Number(yInput?.value) || 50;
			var s = Number(scaleInput?.value) || 100;
			cropImage.style.objectPosition = x + '% ' + y + '%';
			cropImage.style.transform = 'scale(' + (s / 100) + ')';

			document.querySelectorAll('[data-wpdsac-admin-avatar]').forEach(function(img) {
				img.setAttribute('style', 'object-position:' + x + '% ' + y + '%;border-radius:50%;object-fit:cover;transform:scale(' + (s / 100) + ')');
			});
			if (avatarPreview) {
				avatarPreview.style.objectPosition = x + '% ' + y + '%';
				avatarPreview.style.transform = 'scale(' + (s / 100) + ')';
			}
		};

		const commitCrop = () => {
			var x = Number(xInput?.value) || 50;
			var y = Number(yInput?.value) || 50;
			var s = Number(scaleInput?.value) || 100;
			if (xInput) xInput.value = x;
			if (yInput) yInput.value = y;
			if (scaleInput) scaleInput.value = s;
			if (xInput) xInput.dispatchEvent(new Event('change', {bubbles: true}));
		};

		const openCrop = () => {
			var avatarId = document.querySelector('[data-wpdsac-avatar-id]')?.value;
			if (!avatarId || avatarId === '0') return;
			var fullUrl = cropImage.src;
			if (!fullUrl || fullUrl === defaultAvatar) return;
			cropModal.hidden = false;
			document.body.classList.add('wpdsac-modal-open');
			updateCropPreview();
			if (cropZoomVal) cropZoomVal.textContent = (Number(scaleInput?.value) || 100) + '%';
		};

		const closeCrop = () => {
			cropModal.hidden = true;
			document.body.classList.remove('wpdsac-modal-open');
			commitCrop();
			var form = document.querySelector('[data-wpdsac-settings-form]');
			if (form && typeof form.requestSubmit === 'function') {
				form.requestSubmit();
			} else if (form) {
				form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
			}
		};

		if (cropButton) cropButton.addEventListener('click', openCrop);

		document.querySelectorAll('[data-wpdsac-crop-close]').forEach(function(el) {
			el.addEventListener('click', closeCrop);
		});

		document.querySelector('[data-wpdsac-crop-save]')?.addEventListener('click', closeCrop);

		cropModal.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') closeCrop();
		});

		if (cropZoom) {
			cropZoom.addEventListener('input', function() {
				if (cropZoomVal) cropZoomVal.textContent = this.value + '%';
				if (scaleInput) scaleInput.value = this.value;
				updateCropPreview();
			});
		}

		document.querySelector('[data-wpdsac-crop-reset]')?.addEventListener('click', function() {
			if (xInput) xInput.value = 50;
			if (yInput) yInput.value = 50;
			if (scaleInput) scaleInput.value = 100;
			if (cropZoom) cropZoom.value = 100;
			if (cropZoomVal) cropZoomVal.textContent = '100%';
			updateCropPreview();
		});

		const cropMask = document.querySelector('.wpdsac-crop-mask');
		if (cropMask) {
			cropMask.addEventListener('mousedown', function(e) {
				e.preventDefault();
				dragState = {
					startX: e.clientX,
					startY: e.clientY,
					posX: Number(xInput?.value) || 50,
					posY: Number(yInput?.value) || 50,
				};
			});

			window.addEventListener('mousemove', function(e) {
				if (!dragState || cropModal.hidden) return;
				var dx = e.clientX - dragState.startX;
				var dy = e.clientY - dragState.startY;
				var s = Number(scaleInput?.value) || 100;
				// At scale=s%, the image is s/100 times larger than the mask.
				// One pixel of mouse movement corresponds to 200/(200*s/100) in position %.
				// Simplified: each pixel = 100/(200 * s/100) = 50/s position percent.
				var sensitivity = 50 / Math.max(s, 50);
				var px = dragState.posX - dx * sensitivity;
				var py = dragState.posY - dy * sensitivity;
				px = Math.round(Math.min(100, Math.max(0, px)));
				py = Math.round(Math.min(100, Math.max(0, py)));
				if (xInput) xInput.value = px;
				if (yInput) yInput.value = py;
				updateCropPreview();
			});

			window.addEventListener('mouseup', function() {
				dragState = null;
			});

			cropMask.addEventListener('wheel', function(e) {
				e.preventDefault();
				var s = Number(scaleInput?.value) || 100;
				var delta = e.deltaY > 0 ? -5 : 5;
				s = Math.min(200, Math.max(50, s + delta));
				if (scaleInput) scaleInput.value = s;
				if (cropZoom) cropZoom.value = s;
				if (cropZoomVal) cropZoomVal.textContent = s + '%';
				updateCropPreview();
			}, {passive: false});
		}

		document.querySelector('[data-wpdsac-avatar-remove]')?.addEventListener('click', function() {
			if (cropButton) cropButton.hidden = true;
			if (xInput) xInput.value = 50;
			if (yInput) yInput.value = 50;
			if (scaleInput) scaleInput.value = 100;
		});
	}

	document.querySelectorAll('[data-wpdsac-action-repeater]').forEach((repeater) => {
		const rows = repeater.querySelector('[data-wpdsac-action-rows]');
		const template = repeater.querySelector('[data-wpdsac-action-template]');
		const addButton = repeater.querySelector('[data-wpdsac-add-action]');
		let nextIndex = Array.from(rows.querySelectorAll('input[name]')).reduce((highest, input) => {
			const match = input.name.match(/quick_custom_actions\]\[(\d+)\]/);
			return match ? Math.max(highest, Number(match[1]) + 1) : highest;
		}, 0);

		const refresh = () => {
			addButton.disabled = rows.querySelectorAll('[data-wpdsac-action-row]').length >= 8;
		};

		addButton?.addEventListener('click', () => {
			if (!template || rows.querySelectorAll('[data-wpdsac-action-row]').length >= 8) {
				return;
			}

			const wrapper = document.createElement('div');
			wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
			nextIndex += 1;
			const row = wrapper.firstElementChild;
			rows.appendChild(row);
			row.querySelector('input')?.focus();
			row.dispatchEvent(new Event('input', {bubbles: true}));
			refresh();
		});

		repeater.addEventListener('click', (event) => {
			const remove = event.target.closest('[data-wpdsac-remove-action]');
			if (!remove) {
				return;
			}

			remove.closest('[data-wpdsac-action-row]')?.remove();
			repeater.dispatchEvent(new Event('input', {bubbles: true}));
			refresh();
		});

		refresh();
	});

	const preview = document.querySelector('[data-wpdsac-preview]');

  if (!preview) {
    return;
  }

  const resolveGreetingTemplate = (text) => {
    const hour = new Date().getHours();
    const russian = /[а-яё]/iu.test(text);
    const greeting = hour >= 5 && hour < 12
      ? (russian ? 'Доброе утро!' : 'Good morning!')
      : hour >= 12 && hour < 17
        ? (russian ? 'Добрый день!' : 'Good afternoon!')
        : hour >= 17 && hour < 23
          ? (russian ? 'Добрый вечер!' : 'Good evening!')
          : (russian ? 'Доброй ночи!' : 'Good night!');

    return text
      .replace(/\{time_greeting\}/giu, greeting)
      .replace(/доброе\s*\/\s*(?:ый|добрый)\s*\(\s*утро\s*[,\/]\s*день\s*[,\/]\s*вечер\s*[,\/]\s*ночь\s*\)\s*!?/giu, greeting)
      .replace(/доброе\s+утро\s*\/\s*добрый\s+день\s*\/\s*добрый\s+вечер\s*\/\s*доброй\s+ночи\s*!?/giu, greeting);
  };

  document.querySelectorAll('[data-wpdsac-css-var]').forEach((input) => {
    const update = () => {
      preview.style.setProperty(
        input.dataset.wpdsacCssVar,
        `${input.value}${input.dataset.wpdsacUnit || ''}`
      );

      if (input.name === 'wpdsac_settings[launcher_anim_intensity]') {
        const intensity = Math.min(100, Math.max(0, Number(input.value) || 0));
        preview.style.setProperty('--wpdsac-launcher-scale', String(1 + intensity / 1000));
        preview.style.setProperty('--wpdsac-launcher-float', `${2 + intensity / 20}px`);
      }

      if (input.type === 'color' && input.nextElementSibling?.tagName === 'CODE') {
        input.nextElementSibling.textContent = input.value;
      }
    };

    input.addEventListener('input', update);
  });

  document.querySelectorAll('[data-wpdsac-preview-text]').forEach((input) => {
    const target = preview.querySelector(input.dataset.wpdsacPreviewText);

    if (!target) {
      return;
    }

    input.addEventListener('input', () => {
      const rawText = input.value.trim() || target.dataset.wpdsacPreviewFallback || '';
      target.textContent = resolveGreetingTemplate(rawText);
    });
  });

  document.querySelectorAll('[data-wpdsac-preview-placeholder]').forEach((input) => {
    const target = preview.querySelector(input.dataset.wpdsacPreviewPlaceholder);

    if (!target) {
      return;
    }

    input.addEventListener('input', () => {
      target.placeholder = input.value;
    });
  });

  const fontStacks = {
    system: '-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
    modern: 'Arial,"Helvetica Neue",sans-serif',
    rounded: 'ui-rounded,"Arial Rounded MT Bold",sans-serif',
    mono: 'ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace',
  };
  const fontSelect = document.querySelector('[data-wpdsac-font-select]');

  if (fontSelect) {
    fontSelect.addEventListener('change', () => {
      preview.style.setProperty(
        '--wpdsac-font-family',
        fontStacks[fontSelect.value] || fontStacks.system
      );
    });
  }

  const launcherAnimationSelect = document.querySelector('select[data-wpdsac-launcher-animation]');

  if (launcherAnimationSelect) {
    launcherAnimationSelect.addEventListener('change', () => {
      preview.dataset.wpdsacLauncherAnimation = launcherAnimationSelect.value;
    });
  }

  const iconToggle = document.querySelector('[data-wpdsac-preview-icon]');

  if (iconToggle) {
    iconToggle.addEventListener('change', () => {
      preview.classList.toggle('wpdsac-hide-header-icon', !iconToggle.checked);
    });
  }

	const typingPreviewButton = document.querySelector('[data-wpdsac-preview-typing]');
	const typingPreviewMessage = preview.querySelector('[data-wpdsac-preview-message]');
	let typingPreviewRun = 0;

	if (typingPreviewButton && typingPreviewMessage) {
		typingPreviewButton.addEventListener('click', async () => {
			const run = ++typingPreviewRun;
			const text = typingPreviewMessage.textContent.trim() || typingPreviewMessage.dataset.wpdsacPreviewFallback || '';
			const enabled = document.querySelector('[data-wpdsac-message-animation]')?.checked !== false;
			const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			const delayInput = document.querySelector('[name="wpdsac_settings[message_word_delay]"]');
			const delay = Math.min(250, Math.max(20, Number.parseInt(delayInput?.value || '70', 10)));
			const words = text.match(/\S+\s*/gu) || [];

			if (!enabled || reduceMotion || words.length < 2) {
				typingPreviewMessage.textContent = text;
				return;
			}

			typingPreviewButton.disabled = true;
			typingPreviewMessage.textContent = '';
			typingPreviewMessage.classList.add('is-typing');

			for (const word of words) {
				if (run !== typingPreviewRun) break;
				const span = document.createElement('span');
				span.className = 'wpdsac-chat__typing-word';
				span.textContent = word;
				typingPreviewMessage.appendChild(span);
				await new Promise((resolve) => window.setTimeout(resolve, delay));
			}

			typingPreviewMessage.classList.remove('is-typing');
			typingPreviewMessage.textContent = text;
			typingPreviewButton.disabled = false;
		});
	}

  const previewPanel = preview.querySelector('[data-wpdsac-preview-panel]');
  const previewStateButtons = document.querySelectorAll('[data-wpdsac-preview-state]');

  previewStateButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const expanded = button.dataset.wpdsacPreviewState === 'expanded';

      preview.classList.toggle('is-expanded', expanded);

      if (previewPanel) {
        previewPanel.hidden = !expanded;
      }

      previewStateButtons.forEach((stateButton) => {
        const active = stateButton === button;
        stateButton.classList.toggle('is-active', active);
        stateButton.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    });
  });

  const toRgba = (hex, alpha) => {
    if (!hex || alpha <= 0) return 'transparent';
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    const a = Math.min(1, Math.max(0, alpha / 100));
    return `rgb(${r} ${g} ${b} / ${a})`;
  };

  const getValue = (name) => {
    const el = document.querySelector(`[name="wpdsac_settings[${name}]"]`);
    if (!el) return null;
    if (el.type === 'checkbox') return el.checked;
    return el.value;
  };

  const updateComposer = () => {
    const bgColor = getValue('composer_bg_color') || '#ffffff';
    const bgOpacity = Number(getValue('composer_bg_opacity')) || 90;
    preview.style.setProperty('--wpdsac-composer-bg', toRgba(bgColor, bgOpacity));

    const scrollable = getValue('composer_scrollable');
    const quickActions = preview.querySelector('[data-wpdsac-quick-actions]');
    if (quickActions) {
      if (scrollable) {
        quickActions.setAttribute('data-wpdsac-scrollable', '');
      } else {
        quickActions.removeAttribute('data-wpdsac-scrollable');
      }
    }
  };

  const updateMessagesWindow = () => {
    const mode = getValue('messages_bg_mode') || 'transparent';
    const bgColor = getValue('messages_bg_color') || '#ffffff';
    const bgOpacity = Number(getValue('messages_bg_opacity')) || 12;
    const borderColor = getValue('messages_border_color') || '#ffffff';
    const borderOpacity = Number(getValue('messages_border_opacity')) || 45;
    const glareIntensity = Math.min(100, Math.max(0, Number(getValue('messages_glare')) || 40));
    const shadowIntensity = Math.min(100, Math.max(0, Number(getValue('messages_shadow')) || 12));

    const bg = mode === 'transparent' ? 'transparent' : toRgba(bgColor, bgOpacity);
    preview.style.setProperty('--wpdsac-msg-bg', bg);
    preview.style.setProperty('--wpdsac-msg-border-color', toRgba(borderColor, borderOpacity));
    preview.style.setProperty('--wpdsac-msg-glare', `inset 0 1px 0 rgb(255 255 255 / ${(glareIntensity / 100 * 0.4).toFixed(3)})`);
    preview.style.setProperty('--wpdsac-msg-shadow', `0 12px 30px rgb(15 23 42 / ${(shadowIntensity / 100 * 0.4).toFixed(3)})`);
  };

  [
    'composer_bg_color', 'composer_bg_opacity', 'composer_scrollable',
    'messages_bg_mode', 'messages_bg_color', 'messages_bg_opacity',
    'messages_border_color', 'messages_border_opacity',
    'messages_glare', 'messages_shadow',
  ].forEach((name) => {
    const el = document.querySelector(`[name="wpdsac_settings[${name}]"]`);
    if (!el) return;
    el.addEventListener('input', () => {
      if (name.startsWith('composer_')) updateComposer();
      if (name.startsWith('messages_')) updateMessagesWindow();
    });
    el.addEventListener('change', () => {
      if (name.startsWith('composer_')) updateComposer();
      if (name.startsWith('messages_')) updateMessagesWindow();
    });
  });
})();
