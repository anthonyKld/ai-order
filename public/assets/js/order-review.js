(() => {
  'use strict';
  const root = document.getElementById('ai-result');
  if (!root) return;
  const $ = id => document.getElementById(id);
  const catalog = new Map();
  const modal = new bootstrap.Modal($('product-picker'));
  let items = [], revision = Number(root.dataset.revision), generation = 0;
  let confirmed = false, busy = false, conflict = false, queue = Promise.resolve();
  let saveKey = null;
  let pickerIndex = null, pickerPage = 1, pickerPages = 1, searchVersion = 0, searchTimer;
  const money = cents => cents === null ? '—' : `R$ ${(BigInt(cents) / 100n).toLocaleString('pt-BR')},${String(BigInt(cents) % 100n).padStart(2, '0')}`;
  const cents = price => BigInt(String(price).replace('.', ''));
  const quantity = value => /^[1-9][0-9]*$/.test(String(value)) && Number(value) <= 10000 ? Number(value) : null;
  const node = (tag, text, className = '') => {
    const el = document.createElement(tag); el.textContent = text; el.className = className; return el;
  };
  const status = (text, type = 'info') => {
    $('review-status').className = `alert alert-${type}`; $('review-status').textContent = text;
  };
  const lock = value => {
    busy = value; $('review-fields').disabled = value;
    $('ai-submit').disabled = value; $('ai-message').readOnly = value;
    buttons();
  };
  const buttons = () => {
    $('review-save').hidden = !confirmed;
    $('review-save').disabled = busy || conflict || !confirmed || !saveKey;
    $('review-confirm').disabled = busy || conflict || confirmed || !items.length;
    $('review-validate').disabled = busy || conflict;
    $('add-product').disabled = busy || items.length >= 100;
  };
  const payload = () => ({ items: items.map(i => ({ product_id: i.product_id, variant_id: i.variant_id, quantity: i.quantity })),
    notes: $('ai-notes').value, requested_deadline: $('ai-deadline').value });
  const post = async (action, data = {}) => {
    const response = await fetch(root.dataset.endpoint, {
      method: 'POST', credentials: 'same-origin', keepalive: action === 'invalidate',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': $('ai-form').dataset.token },
      body: JSON.stringify({ ...data, action, revision }),
    });
    const result = await response.json();
    if (response.status === 409) { conflict = true; buttons(); throw new Error(result.error.message); }
    if (Number.isInteger(result.revision)) revision = result.revision;
    if (result.error) throw new Error(result.error.message);
    return result;
  };
  const schedule = task => {
    const next = queue.then(task);
    queue = next.catch(error => { status(error.message || 'Não foi possível comunicar com o servidor. Tente novamente.', 'danger'); });
    return next;
  };
  const changed = () => {
    generation++;
    if (confirmed) {
      confirmed = false;
      // Enfileira a invalidação antes de qualquer confirmação posterior.
      schedule(() => post('invalidate')).catch(() => {});
    }
    status('Prévia alterada. Confirme os dados para validar preços e estoque atuais.');
    buttons();
  };
  const productFor = item => catalog.get(Number(item.product_id));
  const stockFor = (product, item) => product?.variants.length
    ? product.variants.find(v => v.id === Number(item.variant_id))?.stock ?? null : product?.stock ?? null;
  const local = () => {
    const sums = new Map();
    for (const item of items) {
      const key = `${item.product_id}:${item.variant_id}`;
      sums.set(key, (sums.get(key) || 0) + (quantity(item.quantity) || 0));
    }
    let total = 0n, units = 0, pending = false;
    items.forEach((item, index) => {
      const product = productFor(item), stock = stockFor(product, item), qty = quantity(item.quantity);
      const issues = {};
      if (!product) issues.product_id = 'Selecione um produto ativo.';
      if (product?.variants.length && stock === null) issues.variant_id = 'Selecione uma variação.';
      if (!qty) issues.quantity = 'Use um número inteiro entre 1 e 10.000.';
      else if (stock !== null && sums.get(`${item.product_id}:${item.variant_id}`) > stock) issues.quantity = `Quantidade somada: ${sums.get(`${item.product_id}:${item.variant_id}`)}. Estoque disponível: ${stock}.`;
      const errors = { ...issues, ...(item.serverErrors || {}) };
      const ready = Object.keys(errors).length === 0;
      const subtotal = ready && product ? cents(product.price) * BigInt(qty) : null;
      total += subtotal || 0n; units += qty || 0; pending ||= !ready;
      const row = $('ai-items').children[index];
      row.classList.toggle('table-warning', !ready);
      row.querySelector('[data-price]').textContent = product ? money(cents(product.price)) : '—';
      row.querySelector('[data-subtotal]').textContent = money(subtotal);
      row.querySelector('[data-stock]').textContent = stock ?? '—';
      const state = row.querySelector('[data-status]'); state.replaceChildren(node('span', ready ? 'Pronto para conferir' : 'Requer correção', `badge text-bg-${ready ? 'success' : 'warning'}`));
      for (const [field, message] of Object.entries(errors)) {
        const text = node('div', message, 'small text-danger mt-1'); text.id = `item-${index}-${field}-error`; state.append(text);
      }
      if (item.hint) state.append(node('div', item.hint, 'small text-secondary mt-1'));
      for (const [field, selector] of [['quantity','input'], ['variant_id','select'], ['product_id','button']]) {
        const control = row.querySelector(selector);
        control.setAttribute('aria-invalid', String(Boolean(errors[field])));
        if (errors[field]) control.setAttribute('aria-describedby', `item-${index}-${field}-error`);
        else control.removeAttribute('aria-describedby');
      }
    });
    $('ai-total').textContent = money(total); $('review-units').textContent = units;
    $('ai-total-label').textContent = pending ? 'Total parcial estimado dos itens sem pendências' : 'Total estimado dos itens';
    buttons();
    return !pending;
  };
  const render = () => {
    $('ai-items').replaceChildren();
    items.forEach((item, index) => {
      const row = node('tr', '');
      const product = productFor(item);
      const cell = node('td', '');
      cell.append(node('div', product?.name ?? item.label ?? 'Produto não identificado', 'fw-semibold'));
      const choose = node('button', product ? 'Trocar produto' : 'Selecionar produto', 'btn btn-sm btn-outline-primary mt-2');
      choose.type = 'button'; choose.addEventListener('click', () => openPicker(index)); cell.append(choose); row.append(cell);
      const variantCell = node('td', ''); const select = node('select', '', 'form-select form-select-sm');
      select.setAttribute('aria-label', `Variação do item ${index + 1}`);
      select.append(new Option(product?.variants.length ? 'Selecione…' : 'Sem variação', ''));
      for (const v of product?.variants || []) select.append(new Option(v.name, v.id));
      select.value = item.variant_id ?? ''; select.disabled = !product?.variants.length;
      select.addEventListener('change', () => { item.variant_id = select.value ? Number(select.value) : null; item.serverErrors = {}; item.hint = ''; changed(); local(); });
      variantCell.append(select); row.append(variantCell);
      const quantityCell = node('td', ''); const input = node('input', '', 'form-control form-control-sm review-quantity');
      Object.assign(input, { type: 'number', min: '1', max: '10000', step: '1', required: true, value: item.quantity ?? '' });
      input.setAttribute('aria-label', `Quantidade do item ${index + 1}`);
      input.addEventListener('input', () => { item.quantity = input.value; item.hint = ''; items.forEach(i => { i.serverErrors = {}; }); changed(); local(); });
      quantityCell.append(input); row.append(quantityCell);
      for (const name of ['price', 'subtotal', 'stock', 'status']) { const td = node('td', ''); td.dataset[name] = ''; row.append(td); }
      const actions = node('td', ''); const remove = node('button', 'Remover', 'btn btn-sm btn-outline-danger'); remove.type = 'button';
      remove.setAttribute('aria-label', `Remover item ${index + 1}`);
      remove.addEventListener('click', () => { items.splice(index, 1); items.forEach(i => { i.serverErrors = {}; }); changed(); render(); });
      actions.append(remove); row.append(actions); $('ai-items').append(row);
    });
    local();
  };
  const loadProduct = async id => {
    if (!id) return;
    const response = await fetch(`${root.dataset.catalog}?id=${encodeURIComponent(id)}`);
    const data = await response.json();
    if (!response.ok) throw new Error(data.error?.message || 'Falha ao carregar produto.');
    catalog.delete(Number(id));
    for (const product of data.products) catalog.set(product.id, product);
  };
  const hydrate = async () => {
    await Promise.all([...new Set(items.map(i => i.product_id).filter(Boolean))].map(loadProduct));
  };
  const validate = async action => {
    if (busy || conflict) return;
    confirmed = false;
    status(action === 'confirm' ? 'Confirmando os dados…' : 'Conferindo preços e estoque…');
    lock(true);
    const current = generation;
    try {
      const data = await schedule(() => post(action, payload()));
      if (current !== generation) return;
      items.forEach((item, i) => {
        const validated = data.validated_items[i];
        item.serverErrors = validated?.errors || {};
        const product = productFor(item);
        if (product && validated?.unit_price !== null && validated?.unit_price !== undefined) {
          product.price = validated.unit_price;
          if (product.variants.length) { const variant = product.variants.find(v => v.id === Number(item.variant_id)); if (variant && validated.available_stock !== null) variant.stock = validated.available_stock; }
          else if (validated.available_stock !== null) product.stock = validated.available_stock;
        }
      });
      confirmed = data.confirmed;
      saveKey = data.idempotency_key ?? null;
      local();
      $('ai-total').textContent = money(cents(data.estimated_total));
      $('review-units').textContent = data.total_units;
      $('notes-error').textContent = data.errors.notes || '';
      $('deadline-error').textContent = data.errors.requested_deadline || '';
      $('ai-warnings').replaceChildren(...data.warnings.map(w => node('p', w, 'alert alert-warning')));
      status(confirmed ? `${data.message} O pedido ainda não foi registrado definitivamente.` : data.success
        ? 'Prévia validada. Clique em Confirmar dados para manter a confirmação temporária.'
        : data.errors.items || 'Corrija as pendências indicadas e tente novamente.', confirmed ? 'success' : data.success ? 'info' : 'warning');
    } catch (error) { status(error.message || 'Falha de comunicação. Seus dados continuam na tela.', 'danger'); }
    finally { lock(false); }
  };
  const pickerSelection = () => {
    const product = catalog.get(Number($('picker-product').value));
    const select = $('picker-variant'); select.replaceChildren(new Option(product?.variants.length ? 'Selecione…' : 'Sem variação', ''));
    for (const variant of product?.variants || []) select.append(new Option(variant.name, variant.id));
    select.required = Boolean(product?.variants.length); select.disabled = !product?.variants.length;
    pickerInfo();
  };
  const pickerInfo = () => {
    const product = catalog.get(Number($('picker-product').value));
    const stock = stockFor(product, { variant_id: $('picker-variant').value });
    $('picker-info').textContent = product ? `Preço: ${money(cents(product.price))} · Estoque: ${stock ?? 'selecione a variação'}` : '';
  };
  const search = async () => {
    const version = ++searchVersion;
    $('picker-submit').disabled = true; $('picker-error').textContent = '';
    try {
      const response = await fetch(`${root.dataset.catalog}?search=${encodeURIComponent($('product-search').value)}&page=${pickerPage}`);
      const data = await response.json();
      if (version !== searchVersion) return;
      if (!response.ok) throw new Error(data.error?.message || 'Falha ao pesquisar produtos.');
      $('picker-product').replaceChildren(new Option('Selecione um produto…', ''));
      for (const product of data.products) { catalog.set(product.id, product); $('picker-product').append(new Option(product.name, product.id)); }
      pickerPage = data.page; pickerPages = data.pages;
      $('picker-page').textContent = `Página ${pickerPage} de ${pickerPages}`;
      $('picker-prev').disabled = pickerPage <= 1; $('picker-next').disabled = pickerPage >= pickerPages;
      if (!data.products.length) $('picker-error').textContent = 'Nenhum produto ativo encontrado.';
      pickerSelection(); $('picker-submit').disabled = false;
    } catch (error) { if (version === searchVersion) $('picker-error').textContent = error.message; }
  };
  function openPicker(index) {
    pickerIndex = index; pickerPage = 1; $('product-search').value = '';
    $('picker-quantity').value = index === null ? '1' : items[index].quantity || '1';
    $('picker-title').textContent = index === null ? 'Adicionar produto' : 'Corrigir produto';
    modal.show(); search();
  }
  $('product-picker').addEventListener('shown.bs.modal', () => $('product-search').focus());
  $('product-search').addEventListener('input', () => { clearTimeout(searchTimer); ++searchVersion; $('picker-submit').disabled = true; searchTimer = setTimeout(() => { pickerPage = 1; search(); }, 250); });
  $('picker-prev').addEventListener('click', () => { pickerPage = Math.max(1, pickerPage - 1); search(); });
  $('picker-next').addEventListener('click', () => { pickerPage = Math.min(pickerPages, pickerPage + 1); search(); });
  $('picker-product').addEventListener('change', pickerSelection);
  $('picker-variant').addEventListener('change', pickerInfo);
  $('picker-form').addEventListener('submit', event => {
    event.preventDefault();
    if ($('picker-submit').disabled || !$('picker-form').reportValidity() || !quantity($('picker-quantity').value)) return;
    const product = catalog.get(Number($('picker-product').value));
    if (!product || (pickerIndex === null && items.length >= 100)) return;
    const item = { product_id: product.id, variant_id: $('picker-variant').value ? Number($('picker-variant').value) : null, quantity: $('picker-quantity').value };
    if (pickerIndex === null) items.push(item); else items[pickerIndex] = item;
    items.forEach(i => { i.serverErrors = {}; }); changed(); render(); modal.hide(); validate('validate');
  });
  $('add-product').addEventListener('click', () => openPicker(null));
  $('review-form').addEventListener('submit', event => { event.preventDefault(); validate('confirm'); });
  $('review-validate').addEventListener('click', () => validate('validate'));
  $('review-save').addEventListener('click', async () => {
    if (busy || conflict || !confirmed || !saveKey) return;
    lock(true); $('order-saving').hidden = false;
    status('Salvando pedido e atualizando o estoque…');
    try {
      await queue;
      const response = await fetch(root.dataset.save, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': $('ai-form').dataset.token },
        body: JSON.stringify({ idempotency_key: saveKey }),
      });
      const data = await response.json();
      if (!response.ok) {
        if (data.preview) {
          confirmed = false; saveKey = null; revision = data.revision;
          items.forEach((item, i) => { item.serverErrors = data.preview.validated_items[i]?.errors || {}; });
          await hydrate(); render();
        }
        throw new Error(data.error?.message || 'Não foi possível salvar. Tente novamente.');
      }
      status(`Pedido ${data.order_number} salvo com sucesso.`, 'success');
      window.location.assign(data.redirect);
    } catch (error) { status(error.message || 'Falha de comunicação. Tente salvar novamente.', 'danger'); }
    finally { $('order-saving').hidden = true; lock(false); }
  });
  for (const id of ['ai-notes', 'ai-deadline']) $(id).addEventListener('input', () => { $('notes-error').textContent = ''; $('deadline-error').textContent = ''; changed(); });
  window.addEventListener('order:revision', event => { if (Number.isInteger(event.detail)) revision = event.detail; });
  window.addEventListener('order:busy', event => {
    lock(event.detail);
    if (event.detail) {
      if (confirmed) schedule(() => post('invalidate')).catch(() => {});
      confirmed = false; status('Interpretando uma nova mensagem…');
    }
  });
  const interpret = async data => {
    generation++; confirmed = false;
    items = data.items.map(i => ({ product_id: i.product_id, variant_id: i.variant_id ?? null, quantity: i.quantity,
      label: i.requested_product ?? i.product_name, hint: i.issues.map(issue => issue.message).join(' ') }));
    $('ai-notes').value = data.notes ?? ''; $('ai-deadline').value = data.requested_deadline ?? '';
    $('ai-warnings').replaceChildren(...data.warnings.map(w => node('p', w, 'alert alert-warning')));
    render(); status('Revise os produtos, variações e quantidades antes de confirmar.'); $('result-title').focus();
    try { await hydrate(); render(); }
    catch (error) { status(error.message, 'danger'); }
  };
  // Serializa a interpretação com a sessão e aguarda a carga do catálogo antes de liberar a edição.
  window.orderReview = { ready: () => queue, interpret };
  const saved = JSON.parse(root.dataset.preview);
  if (saved) {
    items = saved.items; $('ai-notes').value = saved.notes ?? ''; $('ai-deadline').value = saved.requested_deadline ?? '';
    confirmed = true;
    saveKey = saved.idempotency_key ?? null;
    status('Confirmação temporária recuperada. O pedido ainda não foi registrado. Revalide para conferir preços e estoque atuais.', 'success');
    lock(true);
    hydrate().then(render).catch(error => status(error.message, 'danger')).finally(() => lock(false));
  }
  render();
})();
