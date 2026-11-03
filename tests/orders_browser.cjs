// Executado por orders_mysql.php --browser contra um banco aleatório e isolado.
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require(require.resolve('playwright', { paths: [process.cwd(), path.join(__dirname, '../.qa')] }));

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  let checks = 0;
  const check = (condition, message) => { assert.ok(condition, message); checks++; };
  const base = process.env.AI_ORDER_TEST_URL;
  try {
    const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
    const jsErrors = []; page.on('pageerror', e => jsErrors.push(e.message));
    await page.goto(`${base}/assistente`);
    let revision = Number(await page.locator('#ai-result').getAttribute('data-revision'));
    const post = async (data, options = {}) => {
      const result = await page.evaluate(async ({ base, data, options, revision }) => {
        const response = await fetch(`${base}/api/orders/validate`, { method: 'POST', headers: {
          'Content-Type': options.type || 'application/json', ...(options.csrf === false ? {} : { 'X-CSRF-Token': document.getElementById('ai-form').dataset.token }),
        }, body: options.raw ?? JSON.stringify({ action: 'confirm', revision, ...data }) });
        return { status: response.status, data: await response.json() };
      }, { base, data, options, revision });
      if (Number.isInteger(result.data.revision)) revision = result.data.revision;
      return result;
    };
    const row = { product_id: 1, variant_id: 1, quantity: 3 };
    for (const [options, status] of [[{csrf:false},403],[{type:'text/plain'},415],[{raw:'{'},400],[{raw:'x'.repeat(65537)},413]]) {
      check((await post({items:[row]},options)).status === status, `Proteção HTTP ${status}.`);
    }
    for (const data of [
      {items:[]}, {items:[{...row,quantity:0}]}, {items:[{...row,quantity:-1}]}, {items:[{...row,quantity:1.5}]},
      {items:[{...row,product_id:999}]}, {items:[{...row,product_id:2}]}, {items:[{...row,product_id:3}]},
      {items:[{...row,quantity:11}]}, {items:[{...row,quantity:6},{...row,quantity:5}]},
      {items:[row],notes:'a'.repeat(2001)}, {items:[row],requested_deadline:'a'.repeat(201)},
    ]) {
      const response = await post(data);
      check(response.status === 422 && !response.data.confirmed, 'Confirmação inválida rejeitada no servidor.');
    }
    let response = await post({ items: [{...row,unit_price:'0.01'}], estimated_total:'0.03', requested_deadline:'sexta-feira' });
    check(response.data.confirmed && response.data.estimated_total === '119.70', `Preço do navegador ignorado e confirmação HTTP: ${JSON.stringify(response)}`);
    const snapshot = async () => { await page.reload(); return JSON.parse(await page.locator('#ai-result').getAttribute('data-preview')); };
    check((await snapshot()).requested_deadline === 'sexta-feira', 'Sessão recupera prazo original após recarga.');
    await page.waitForFunction(() => !document.getElementById('review-fields').disabled);
    response = await post({ action:'invalidate' });
    check(response.data.success && !response.data.confirmed && await snapshot() === null, 'Invalidação remove confirmação no servidor.');
    response = await post({items:[row], revision:0});
    check(response.status === 409, 'Requisição antiga não substitui estado atual.');
    const catalog = await (await page.request.get(`${base}/api/orders/catalog?search=Caneca`)).json();
    check(catalog.products.length === 1 && catalog.products[0].id === 2, 'Pesquisa por nome.');
    const inactive = await (await page.request.get(`${base}/api/orders/catalog?id=3`)).json();
    check(inactive.products.length === 0, 'Catálogo exclui inativos.');
    check((await page.request.get(`${base}/api/orders/validate`)).status() === 405, 'GET não confirma.');
    await page.reload();
    let aiCalls = 0;
    await page.route('**/api/ai/interpret', route => {
      aiCalls++;
      return route.fulfill({contentType:'application/json',body:JSON.stringify({success:true,revision,
        items:[{...row,product_name:'Camiseta',requested_product:'Camiseta',issues:[]},
          {product_id:null,variant_id:null,quantity:2,product_name:'<img src=x onerror=alert(1)>',issues:[{message:'Produto não encontrado.'}]}],
        notes:'<script>window.injected=true</script>',requested_deadline:'sexta-feira',warnings:['Confira os produtos.'],
      })});
    });
    await page.locator('#ai-message').fill('Quero três camisetas e dois produtos.');
    await page.locator('#ai-submit').click();
    await page.waitForFunction(() => document.querySelectorAll('#ai-items tr').length === 2 && document.querySelector('#ai-items [data-price]').textContent === 'R$ 39,90');
    check(await page.locator('#ai-items img').count() === 0 && await page.locator('#ai-notes').inputValue() === '<script>window.injected=true</script>', 'Conteúdo de IA exibido com escape.');
    check(await page.locator('#ai-deadline').inputValue() === 'sexta-feira', 'Prazo relativo editável.');
    const applyProduct = async (product, variant, qty) => {
      await page.locator(`#picker-product option[value="${product}"]`).waitFor({state:'attached'});
      await page.locator('#picker-product').selectOption(String(product));
      if (variant) await page.locator('#picker-variant').selectOption(String(variant));
      await page.locator('#picker-quantity').fill(String(qty));
      const request = page.waitForResponse(r => r.url().endsWith('/api/orders/validate') && r.request().postDataJSON().action === 'validate');
      await page.locator('#picker-submit').click(); await request;
      await page.locator('#product-picker').waitFor({state:'hidden'});
      await page.waitForFunction(() => !document.getElementById('review-fields').disabled);
    };
    await page.locator('#ai-items tr').nth(1).getByRole('button',{name:'Selecionar produto'}).click();
    await applyProduct(2,null,2);
    check(await page.locator('#ai-total').innerText() === 'R$ 169,50', 'Produto corrigido e preço atualizado.');
    await page.locator('#ai-items tr').first().locator('select').selectOption('2');
    await page.locator('#ai-items tr').first().locator('input').fill('4');
    check(await page.locator('#ai-total').innerText() === 'R$ 209,40', 'Variação e quantidade recalculam valores.');
    await page.getByRole('button',{name:'Remover item 2',exact:true}).click();
    check(await page.locator('#ai-items tr').count() === 1 && await page.locator('#ai-total').innerText() === 'R$ 159,60', 'Remoção atualiza total.');
    await page.locator('#add-product').click(); await applyProduct(1,2,5);
    check(await page.locator('#ai-items tr.table-warning').count() === 2, 'Estoque duplicado destaca ambas as linhas.');
    await page.locator('#ai-items tr').nth(1).locator('input').fill('2');
    await page.locator('#ai-notes').fill('Embalar separadamente');
    await page.locator('#ai-deadline').fill('sexta-feira à tarde');
    let confirmCalls = 0;
    await page.route('**/api/orders/validate', async route => {
      if (route.request().postDataJSON().action === 'confirm') {
        confirmCalls++; await new Promise(resolve => setTimeout(resolve, 250));
      }
      await route.continue();
    });
    const confirmation = page.waitForResponse(r => r.url().endsWith('/api/orders/validate') && r.request().postDataJSON().action === 'confirm');
    await page.locator('#review-confirm').click();
    check(await page.locator('#review-confirm').isDisabled(), 'Botão desabilitado durante confirmação.');
    await page.locator('#review-form').evaluate(form => form.dispatchEvent(new Event('submit', {cancelable:true})));
    await confirmation;
    await page.waitForFunction(() => document.getElementById('review-status').textContent.includes('Dados confirmados com sucesso'));
    check(await page.locator('#ai-total').innerText() === 'R$ 239,40' && await page.locator('#review-confirm').isDisabled(), 'Confirmação válida e proteção de reenvio.');
    check(confirmCalls === 1, 'Envio concorrente não duplica confirmação.');
    await page.unroute('**/api/orders/validate');
    const saved = await snapshot();
    check(saved.items.length === 2 && saved.notes === 'Embalar separadamente' && saved.requires_revalidation, 'Sessão contém apenas prévia temporária.');
    await page.waitForFunction(() => !document.getElementById('review-fields').disabled);
    const invalidate = page.waitForResponse(r => r.url().endsWith('/api/orders/validate') && r.request().postDataJSON().action === 'invalidate');
    await page.locator('#ai-items input').first().fill('1'); await invalidate;
    check(await page.locator('#review-confirm').isEnabled(), 'Editar exige nova confirmação.');
    check(await snapshot() === null, 'Edição também invalida sessão PHP.');
    await page.locator('#add-product').click(); await applyProduct(2,null,1);
    await page.route('**/api/orders/validate', route => route.fulfill({status:500,contentType:'application/json',body:JSON.stringify({success:false,error:{message:'Falha simulada'}})}));
    await page.locator('#review-confirm').click();
    await page.waitForFunction(() => document.getElementById('review-status').textContent === 'Falha simulada');
    check(await page.locator('#ai-items input').inputValue() === '1' && await page.locator('#review-confirm').isEnabled(), 'Erro mantém dados e permite tentar novamente.');
    for (const width of [390,320]) {
      await page.setViewportSize({width,height:844});
      check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Layout sem transbordar em ${width}px.`);
    }
    await page.screenshot({path:'.qa/orders-mobile.png',fullPage:true});
    await page.setViewportSize({width:1366,height:900});
    await page.screenshot({path:'.qa/orders-desktop.png',fullPage:true});
    check(aiCalls === 1, 'Edições manuais não chamam IA.');
    check(jsErrors.length === 0, `Sem erros JavaScript: ${jsErrors.join(', ')}`);
    console.log(`OK: ${checks} verificações HTTP/navegador da Etapa 04, sem API real.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
