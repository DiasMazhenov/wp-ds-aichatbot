(() => {
  'use strict';

  const settingsWrap = document.querySelector('.wpdsac-settings-wrap');
  const tabs = Array.from(document.querySelectorAll('[data-wpdsac-tab]'));
  const panels = Array.from(document.querySelectorAll('[data-wpdsac-panel]'));
  const storageKey = 'wpdsacActiveSettingsTab';

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
  const providerRows = document.querySelectorAll('.wpdsac-provider-setting');
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
    return diagnostics;
  };

  const updateProviderFields = () => {
    if (!providerSelect) {
      return;
    }

    providerRows.forEach((row) => {
      const active = row.classList.contains(`wpdsac-provider-setting--${providerSelect.value}`);
      row.hidden = !active;
      row.setAttribute('aria-hidden', active ? 'false' : 'true');
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

  if (settingsForm && window.wpdsacAdmin) {
    const markUnsaved = () => {
      if (saveStatus) {
        saveStatus.className = 'wpdsac-save-note is-unsaved';
        saveStatus.textContent = window.wpdsacAdmin.unsavedText;
      }
    };

    settingsForm.addEventListener('input', markUnsaved);
    settingsForm.addEventListener('change', markUnsaved);

    settingsForm.addEventListener('submit', async (event) => {
      event.preventDefault();

      const submitButton = settingsForm.querySelector('[type="submit"]');
      const originalButtonText = submitButton ? submitButton.value : '';
      const formData = new FormData(settingsForm);

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

  const preview = document.querySelector('[data-wpdsac-preview]');

  if (!preview) {
    return;
  }

  document.querySelectorAll('[data-wpdsac-css-var]').forEach((input) => {
    const update = () => {
      preview.style.setProperty(
        input.dataset.wpdsacCssVar,
        `${input.value}${input.dataset.wpdsacUnit || ''}`
      );
    };

    input.addEventListener('input', update);
  });

  document.querySelectorAll('[data-wpdsac-preview-text]').forEach((input) => {
    const target = preview.querySelector(input.dataset.wpdsacPreviewText);

    if (!target) {
      return;
    }

    input.addEventListener('input', () => {
      target.textContent = input.value;
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

  const iconToggle = document.querySelector('[data-wpdsac-preview-icon]');

  if (iconToggle) {
    iconToggle.addEventListener('change', () => {
      preview.classList.toggle('wpdsac-hide-header-icon', !iconToggle.checked);
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
})();
