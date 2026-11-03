(() => {
  'use strict';
  const form = document.getElementById('ai-form');
  if (!form) return;
  const field = document.getElementById('ai-message');
  const submit = document.getElementById('ai-submit');
  const loading = document.getElementById('ai-loading');
  const error = document.getElementById('ai-error');
  const result = document.getElementById('ai-result');
  let pending = false;
  const currency = (value) => {
    if (value === null) return '—';
    const [whole, cents] = value.split('.');
    return `R$ ${whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${cents}`;
  };
  const render = (data) => {
    const rows = document.getElementById('ai-items');
    rows.replaceChildren();
    for (const item of data.items) {
      const row = document.createElement('tr');
      for (const text of [item.product_name, item.variant ?? '—', item.quantity ?? 'Não informada', currency(item.unit_price), currency(item.subtotal)]) {
        const cell = document.createElement('td');
        cell.textContent = text;
        row.append(cell);
      }
      const status = document.createElement('td');
      const label = document.createElement('span');
      label.className = `badge ${item.ready ? 'text-bg-success' : 'text-bg-warning'}`;
      label.textContent = item.ready ? 'Identificado' : 'Requer conferência';
      status.append(label);
      if (item.issues.length) {
        const list = document.createElement('ul');
        list.className = 'small mt-2 mb-0 ps-3';
        for (const issue of item.issues) {
          const li = document.createElement('li');
          li.textContent = issue.message;
          list.append(li);
        }
        status.append(list);
      }
      row.append(status);
      rows.append(row);
    }
    const warnings = document.getElementById('ai-warnings');
    warnings.replaceChildren();
    for (const text of data.warnings) {
      const alert = document.createElement('p');
      alert.className = 'alert alert-warning';
      alert.textContent = text;
      warnings.append(alert);
    }
    document.getElementById('ai-total-label').textContent = data.has_pending_items ? 'Total parcial estimado dos itens sem pendências' : 'Total estimado dos itens';
    document.getElementById('ai-total').textContent = currency(data.estimated_total);
    document.getElementById('ai-deadline').textContent = data.requested_deadline ?? 'Não informado';
    document.getElementById('ai-notes').textContent = data.notes ?? 'Nenhuma observação';
    result.hidden = false;
    document.getElementById('result-title').focus();
  };
  field.addEventListener('input', () => { if (!pending) result.hidden = true; });
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (pending || !form.reportValidity()) return;
    pending = true;
    submit.disabled = true;
    field.readOnly = true;
    loading.hidden = false;
    form.setAttribute('aria-busy', 'true');
    error.hidden = true;
    result.hidden = true;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 75000);
    try {
      const response = await fetch(form.action, {
        method: 'POST', credentials: 'same-origin', signal: controller.signal,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': form.dataset.token },
        body: JSON.stringify({ message: field.value }),
      });
      if (!(response.headers.get('content-type') ?? '').includes('application/json')) {
        throw new Error('O servidor não retornou uma resposta válida. Tente novamente mais tarde.');
      }
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.error?.message ?? 'Não foi possível interpretar o pedido.');
      render(data);
    } catch (failure) {
      error.textContent = failure.name === 'AbortError' ? 'O tempo de espera terminou. Aguarde antes de tentar novamente.'
        : failure instanceof TypeError ? 'Não foi possível comunicar com o servidor. Verifique sua conexão.' : failure.message;
      error.hidden = false;
    } finally {
      clearTimeout(timeout);
      pending = false;
      submit.disabled = false;
      field.readOnly = false;
      loading.hidden = true;
      form.removeAttribute('aria-busy');
    }
  });
})();
