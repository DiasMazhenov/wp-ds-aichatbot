(() => {
  'use strict';

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
})();
