(() => {
  'use strict';
  document.querySelectorAll('[data-order-action]').forEach(form => {
    form.addEventListener('submit', event => {
      if (form.dataset.busy) { event.preventDefault(); return; }
      form.dataset.busy = '1';
      const button = form.querySelector('button');
      button.disabled = true;
      button.textContent = 'Processando…';
      form.setAttribute('aria-busy', 'true');
    });
  });
})();
