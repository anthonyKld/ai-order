// Opcional: usa Playwright já instalado no ambiente de QA, sem dependência na aplicação.
// Simulação do contrato HTTP da aplicação; não valida o provedor.
// O teste real do Gemini deve ser separado e explícito; ver docs/TESTES-GEMINI.md.
const path = require('node:path');
const { chromium } = require(require.resolve('playwright', { paths: [process.cwd(), path.join(__dirname, '../.qa')] }));
const assert = require('node:assert/strict');

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  let checks = 0;
  const check = (condition, message) => { assert.ok(condition, message); checks++; };
  try {
    const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const base = process.env.AI_ORDER_TEST_URL || 'http://127.0.0.1:8000';
    await page.goto(`${base}/assistente`);
    check(await page.title() === 'Assistente Inteligente · AI Order', 'Página real carregada.');
    check(await page.locator('#ai-form').getAttribute('action') === new URL(`${base}/api/ai/interpret`).pathname, 'Endpoint preserva subdiretório.');
    const api = async (body, token = true, contentType = 'application/json') => page.evaluate(async ({ body, token, contentType }) => {
      const form = document.getElementById('ai-form');
      const response = await fetch(form.action, { method: 'POST', headers: {
        'Content-Type': contentType, ...(token ? { 'X-CSRF-Token': form.dataset.token } : {}),
      }, body });
      return { status: response.status, data: await response.json() };
    }, { body, token, contentType });
    for (const [body, token, type, status] of [
      ['{}', false, 'application/json', 403], ['{}', true, 'text/plain', 415],
      ['{', true, 'application/json', 400], ['{"message":""}', true, 'application/json', 422],
      ['{"message":[]}', true, 'application/json', 422], ['null', true, 'application/json', 422],
      [JSON.stringify({ message: 'á'.repeat(2001) }), true, 'application/json', 422],
      [JSON.stringify({ message: 'a'.repeat(16001) }), true, 'application/json', 413],
    ]) {
      const response = await api(body, token, type);
      check(response.status === status && response.data.success === false && response.data.estimated_total === null, `HTTP ${status} com JSON consistente.`);
    }
    const method = await page.request.get(`${base}/api/ai/interpret`);
    check(method.status() === 405, 'GET não interpreta pedidos.');
    let requests = 0;
    await page.route('**/api/orders/catalog?*', route => route.fulfill({ status: 200, contentType: 'application/json',
      body: JSON.stringify({ success: true, products: [{ id: 1, name: '<img src=x onerror=alert(1)>', price: '39.90', stock: 10, variants: [] }], pages: 1, page: 1 }) }));
    const result = {
      success: true, items: [{ product_id: 1, product_name: '<img src=x onerror=alert(1)>', variant: 'Preta / M', quantity: 3,
        unit_price: '39.90', subtotal: '119.70', ready: true, issues: [] },
      { product_id: null, product_name: 'Produto desconhecido', variant: null, quantity: null, unit_price: null, subtotal: null,
        ready: false, issues: [{ code: 'not_found', message: 'Confira o nome do produto.' }] }],
      warnings: ['Existem pendências.'], notes: '<script>window.injected = true</script>', requested_deadline: 'sexta-feira',
      estimated_total: '119.70', has_pending_items: true,
    };
    await page.route('**/api/ai/interpret', async route => {
      requests++;
      check(route.request().headers()['x-csrf-token']?.length === 64, 'Fetch envia CSRF.');
      await new Promise(resolve => setTimeout(resolve, 250));
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(result) });
    });
    await page.locator('#ai-message').fill('Quero três camisetas pretas M e um produto desconhecido.');
    await page.locator('#ai-submit').click();
    check(await page.locator('#ai-submit').isDisabled() && await page.locator('#ai-loading').isVisible(), 'Carregamento e botão desabilitado.');
    await page.locator('#ai-form').evaluate(form => form.dispatchEvent(new Event('submit', { cancelable: true })));
    await page.waitForFunction(() => document.querySelectorAll('#ai-items tr').length === 2 && document.getElementById('ai-total').textContent === 'R$ 119,70');
    check(requests === 1, 'Clique repetido não duplica requisição.');
    check(await page.locator('#ai-total').innerText() === 'R$ 119,70' && /parcial/.test(await page.locator('#ai-total-label').innerText()), 'Total parcial explícito.');
    check(await page.locator('#ai-items img').count() === 0 && await page.locator('#ai-notes script').count() === 0, 'Resposta renderizada como texto, sem XSS.');
    check(await page.locator('#ai-items tr').count() === 2 && /—/.test(await page.locator('#ai-items tr').nth(1).innerText()), 'Itens pendentes sem preço inventado.');
    check(await page.evaluate(() => document.activeElement.id) === 'result-title', 'Foco acessível no resultado.');
    for (const width of [390, 320]) {
      await page.setViewportSize({ width, height: 844 });
      check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Sem transbordamento da página em ${width}px.`);
    }
    await page.locator('#ai-message').fill('Outro pedido');
    check(await page.locator('#ai-items tr').count() === 2, 'Editar mensagem preserva a conferência até uma nova interpretação.');
    await page.unroute('**/api/ai/interpret');
    await page.route('**/api/ai/interpret', route => route.fulfill({ status: 503, contentType: 'application/json',
      body: JSON.stringify({ success: false, error: { message: 'IA temporariamente indisponível.' } }) }));
    await page.locator('#ai-submit').click();
    await page.locator('#ai-error').waitFor({ state: 'visible' });
    check(/indisponível/.test(await page.locator('#ai-error').innerText()) && await page.locator('#ai-submit').isEnabled(), 'Erro permite nova tentativa manual.');
    check(errors.length === 0, 'Sem erros JavaScript.');
    console.log(`OK: ${checks} verificações HTTP/interface; respostas de sucesso simuladas, nenhuma chamada ao Gemini.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
