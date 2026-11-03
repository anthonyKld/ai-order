(() => {
  'use strict';
  const form = document.getElementById('ai-form');
  if (!form) return;
  const field = document.getElementById('ai-message');
  const submit = document.getElementById('ai-submit');
  const loading = document.getElementById('ai-loading');
  const error = document.getElementById('ai-error');
  let pending = false;
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (pending || !form.reportValidity()) return;
    pending = true;
    submit.disabled = true;
    field.readOnly = true;
    loading.hidden = false;
    form.setAttribute('aria-busy', 'true');
    error.hidden = true;
    window.dispatchEvent(new CustomEvent('order:busy', { detail: true }));
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 75000);
    try {
      await window.orderReview.ready();
      const response = await fetch(form.action, {
        method: 'POST', credentials: 'same-origin', signal: controller.signal,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': form.dataset.token },
        body: JSON.stringify({ message: field.value }),
      });
      if (!(response.headers.get('content-type') ?? '').includes('application/json')) {
        throw new Error('O servidor não retornou uma resposta válida. Tente novamente mais tarde.');
      }
      const data = await response.json();
      window.dispatchEvent(new CustomEvent('order:revision', { detail: data.revision }));
      if (!response.ok || !data.success) throw new Error(data.error?.message ?? 'Não foi possível interpretar o pedido.');
      await window.orderReview.interpret(data);
    } catch (failure) {
      error.textContent = failure.name === 'AbortError' ? 'O tempo de espera terminou. Aguarde antes de tentar novamente.'
        : failure instanceof TypeError ? 'Não foi possível comunicar com o servidor. Verifique sua conexão.' : failure.message;
      error.hidden = false;
    } finally {
      clearTimeout(timeout);
      pending = false;
      window.dispatchEvent(new CustomEvent('order:busy', { detail: false }));
      submit.disabled = false;
      field.readOnly = false;
      loading.hidden = true;
      form.removeAttribute('aria-busy');
    }
  });
})();
