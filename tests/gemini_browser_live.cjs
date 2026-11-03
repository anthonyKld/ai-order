// Optativo: envia um pedido real pelo navegador ao Apache, MySQL e Gemini.
// Não usa mocks, não salva pedidos e não altera estoque. Consome quota da API.
const path = require('node:path');
const { chromium } = require(require.resolve('playwright', { paths: [process.cwd(), path.join(__dirname, '../.qa')] }));
const assert = require('node:assert/strict');

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
    const base = process.env.AI_ORDER_TEST_URL || 'http://localhost/ai-order/public';
    await page.goto(`${base}/assistente`);
    await page.locator('#ai-message').fill(process.env.AI_ORDER_TEST_MESSAGE || 'Quero 3 camisetas básicas no tamanho M');
    const started = Date.now();
    const responsePromise = page.waitForResponse(response => response.url().endsWith('/api/ai/interpret') && response.request().method() === 'POST', { timeout: 80000 });
    await page.locator('#ai-submit').click();
    const response = await responsePromise;
    const result = await response.json();
    await page.waitForFunction(() => !document.getElementById('ai-submit').disabled);
    console.log(JSON.stringify({ http: response.status(), seconds: (Date.now() - started) / 1000,
      success: result.success, error: result.error, items: result.items, total: result.estimated_total }));
    await page.screenshot({ path: path.join(__dirname, '../.qa/gemini-live.png'), fullPage: true });
    assert.equal(response.status(), 200, result.error?.message);
    assert.equal(result.success, true);
    assert.ok(await page.locator('#ai-result').isVisible(), 'Prévia real deve aparecer na interface.');
    assert.ok(await page.locator('#ai-error').isHidden(), 'Sem erro de transporte na interface.');
    assert.ok(result.items.length > 0, 'O pedido deve conter itens interpretados.');
    assert.equal(await page.locator('#ai-items tr').count(), result.items.length);
    console.log('OK: pedido real enviado pelo navegador; resposta do Gemini renderizada na interface.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error.message); process.exit(1); });
