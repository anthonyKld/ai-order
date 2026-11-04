const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require(require.resolve('playwright', { paths: [process.cwd(), path.join(__dirname, '../.qa')] }));

(async () => {
  const base = process.env.AI_ORDER_TEST_URL;
  assert.ok(base, 'Execute pelo runner PHP com banco descartável.');
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  let checks = 0;
  const check = (value, label) => { assert.ok(value, label); console.log(`BROWSER ${++checks}: ${label}`); };
  const fixture = action => execFileSync(process.env.AI_ORDER_TEST_PHP, ['tests/order_browser_fixture.php', action]);
  try {
    const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
    const page = await context.newPage(); const errors = []; page.on('pageerror', e => errors.push(e.message));
    await page.goto(base);
    check(await page.locator('h1').count() === 1, 'Dashboard responde');
    await page.getByRole('link', { name: 'Pedidos', exact: true }).click();
    check(await page.locator('h1').innerText() === 'Pedidos.', 'Navegação até histórico real');
    await page.goto(`${base}/produtos/novo`);
    await page.locator('#name').fill('Caneca QA Etapa 05');
    await page.locator('#price').fill('12,35'); await page.locator('#stock').fill('10');
    await page.getByRole('button', { name: 'Salvar produto', exact: true }).click();
    await page.waitForURL('**/produtos');
    check(await page.locator('body').innerText().then(t => t.includes('Produto cadastrado com sucesso')), 'Cadastro de produto pelo formulário');
    const catalog = await (await page.request.get(`${base}/api/orders/catalog?search=Caneca%20QA%20Etapa%2005`)).json();
    const productId = catalog.products[0].id;
    check(catalog.products[0].stock === 10, 'Estoque inicial definido no cadastro');
    await page.goto(`${base}/assistente`);
    let token = await page.locator('#ai-form').getAttribute('data-token');
    const save = async (key, options = {}) => page.request.post(`${base}/api/orders/save`, {
      headers: { 'Content-Type': options.type || 'application/json', ...(options.csrf === false ? {} : { 'X-CSRF-Token': token }) },
      // Buffer conserva bytes inválidos; o cliente pode normalizar strings como JSON válido.
      data: Buffer.from(options.raw ?? JSON.stringify({ idempotency_key: key, ...options.extra })),
    });
    const keyUnknown = 'f'.repeat(64);
    check((await save(keyUnknown,{csrf:false})).status() === 403, 'Salvar sem CSRF rejeitado');
    check((await save(keyUnknown)).status() === 409, 'Salvar sem prévia confirmada rejeitado');
    for (const [options,status] of [[{type:'text/plain'},415],[{raw:'{'},400],[{raw:'x'.repeat(4097)},413]]) {
      const response = await save(keyUnknown,options);
      check(response.status() === status, `Proteção do corpo HTTP ${status}: recebido ${response.status()} ${await response.text()}`);
    }
    check((await save('invalid')).status() === 422, 'Chave inválida rejeitada');
    check((await page.request.get(`${base}/api/orders/save`)).status() === 405, 'GET não salva');
    let calls = 0;
    await page.route('**/api/ai/interpret', route => {
      calls++;
      return route.fulfill({contentType:'application/json',body:JSON.stringify({success:true,
        items:[{product_id:productId,variant_id:null,quantity:3,product_name:'Caneca QA Etapa 05',issues:[]}],
        notes:'<script>window.orderInjected=true</script>',requested_deadline:'sexta-feira',warnings:[],
      })});
    });
    await page.locator('#ai-message').fill('Quero três canecas QA para sexta-feira.');
    await page.locator('#ai-submit').click();
    await page.waitForFunction(() => document.querySelector('#ai-items [data-price]')?.textContent === 'R$ 12,35' && !document.getElementById('review-fields').disabled);
    check(await page.locator('#ai-total').innerText() === 'R$ 37,05', 'Interpretação simulada alimenta conferência real');
    await page.locator('#review-confirm').click();
    await page.locator('#review-save').waitFor({state:'visible'});
    let preview = await (async () => { await page.reload(); return JSON.parse(await page.locator('#ai-result').getAttribute('data-preview')); })();
    const firstKey = preview.idempotency_key;
    await page.waitForFunction(() => !document.getElementById('review-fields').disabled);
    check(await page.locator('#review-save').isEnabled(), 'Confirmação e chave recuperadas após recarga');
    fixture('fail');
    try {
      const failedResponse = page.waitForResponse(r => r.url().endsWith('/api/orders/save'));
      await page.locator('#review-save').click();
      const failed = await failedResponse; const body = await failed.text();
      check(failed.status() === 500 && !body.includes('credential-sentinel') && !body.includes('SQLSTATE'), 'Erro interno real não expõe detalhes ou credenciais');
      await page.waitForFunction(() => !document.getElementById('review-save').disabled);
      check(await page.locator('#ai-items input').inputValue() === '3', 'Falha mantém dados para tentar novamente');
    } finally { fixture('recover'); }
    let saveCalls = 0;
    await page.route('**/api/orders/save', async route => { saveCalls++; await new Promise(resolve => setTimeout(resolve, 250)); await route.continue(); });
    await page.locator('#review-save').click();
    check(await page.locator('#review-save').isDisabled() && await page.locator('#order-saving').isVisible(), 'Carregamento e prevenção de clique duplicado');
    await page.locator('#review-save').evaluate(button => button.dispatchEvent(new Event('click')));
    await page.waitForURL('**/pedidos/detalhes?id=*');
    const firstUrl = page.url(); const firstId = new URL(firstUrl).searchParams.get('id');
    const number = await page.locator('#order-number').innerText();
    check(number.startsWith('AO-') && await page.locator('#saved-order-total').innerText() === 'R$ 37,05', 'Salvamento redireciona para número e valores reais');
    check(saveCalls === 1 && await page.locator('.alert-success').count() === 1, 'Um envio e confirmação visual de sucesso');
    check(await page.locator('script').filter({hasText:'window.orderInjected'}).count() === 0 && !await page.evaluate(() => window.orderInjected), 'Observações maliciosas não executam');
    const repeat = await save(firstKey,{extra:{items:[],total:'0.01',unit_price:'0.01'}});
    check(repeat.status() === 200 && String((await repeat.json()).id) === firstId, 'Reenvio após sucesso retorna pedido existente e ignora preços do cliente');
    const stock = async () => (await (await page.request.get(`${base}/api/orders/catalog?id=${productId}`)).json()).products[0].stock;
    check(await stock() === 7, 'Estoque baixado uma única vez');
    const otherContext = await browser.newContext(); const otherPage = await otherContext.newPage();
    await otherPage.goto(`${base}/assistente`);
    const foreign = await otherPage.request.post(`${base}/api/orders/save`, {headers:{'X-CSRF-Token':await otherPage.locator('#ai-form').getAttribute('data-token')},data:{idempotency_key:firstKey}});
    check(foreign.status() === 409, 'Chave de outra sessão não autoriza reenvio'); await otherContext.close();
    await page.goto(`${base}/assistente`);
    check(await page.locator('#ai-result').getAttribute('data-preview') === 'null', 'Sucesso invalida a prévia da sessão');
    await page.goto(`${base}/pedidos?q=${encodeURIComponent(number)}`);
    check(await page.locator('tbody tr').count() === 1, 'Pedido pesquisável na listagem');
    await page.getByRole('link',{name:'Detalhes',exact:true}).click();
    const statusPost = (id,status,extra={}) => page.request.post(`${base}/pedidos/status`, {form:{_token:token,id:String(id),status,...extra},maxRedirects:0});
    check((await statusPost(firstId,'completed')).status() === 409, 'HTTP impede pular de pendente para concluído');
    check((await statusPost(firstId,'cancelled')).status() === 422, 'HTTP exige confirmação de cancelamento');
    check((await statusPost(firstId,'processing',{_token:'invalid'})).status() === 403, 'Status exige CSRF');
    check((await page.request.get(`${base}/pedidos/status`)).status() === 405, 'GET não altera status');
    check((await page.request.get(`${base}/pedidos/detalhes?id=-1`)).status() === 404, 'ID inválido não abre detalhe');
    await page.getByRole('button',{name:'Iniciar processamento',exact:true}).click();
    await page.waitForFunction(() => document.getElementById('saved-order-status')?.textContent === 'Em processamento');
    check(await stock() === 7, 'Processamento não baixa estoque novamente');
    await page.getByRole('button',{name:'Cancelar pedido',exact:true}).click();
    await page.locator('#cancel-order').waitFor({state:'visible'});
    check(await page.getByRole('button',{name:'Confirmar cancelamento',exact:true}).isVisible(), 'Modal de cancelamento acessível');
    await page.getByRole('button',{name:'Confirmar cancelamento',exact:true}).click();
    await page.waitForFunction(() => document.getElementById('saved-order-status')?.textContent === 'Cancelado');
    check(await stock() === 10 && await page.locator('body').innerText().then(t=>t.includes('Cancelado em')), 'Cancelamento restaura estoque e mostra data');
    check((await statusPost(firstId,'cancelled',{confirm:'1'})).status() === 303 && await stock() === 10, 'Cancelamento repetido não repõe duas vezes');
    check((await statusPost(firstId,'processing')).status() === 409, 'Pedido cancelado não reabre');
    await page.goto(`${base}/pedidos?status=cancelled&q=${encodeURIComponent(number)}`);
    check(await page.locator('tbody tr').count() === 1, 'Histórico cancelado permanece filtrável');
    await page.goto(`${base}/pedidos?q=nenhum-pedido-qa`);
    check(await page.locator('body').innerText().then(t=>t.includes('Nenhum pedido encontrado')), 'Mensagem amigável sem resultados');
    await page.goto(`${base}/pedidos`);
    await page.getByRole('link',{name:'Próxima',exact:true}).click();
    check(new URL(page.url()).searchParams.get('page') === '2', 'Paginação pelo servidor');

    // Confirmação fica obsoleta enquanto outro formulário altera o catálogo.
    await page.goto(`${base}/assistente`);
    token = await page.locator('#ai-form').getAttribute('data-token');
    const revision = Number(await page.locator('#ai-result').getAttribute('data-revision'));
    const confirmed = await page.request.post(`${base}/api/orders/validate`, {headers:{'X-CSRF-Token':token},data:{action:'confirm',revision,items:[{product_id:productId,variant_id:null,quantity:2,unit_price:'0.01'}],estimated_total:'0.02'}});
    check((await confirmed.json()).estimated_total === '24.70', 'Confirmação ignora preço adulterado');
    await page.reload();
    const edit = await context.newPage();
    await edit.goto(`${base}/produtos/editar?id=${productId}`);
    await edit.locator('#price').fill('20,10'); await edit.locator('#name').fill('Caneca QA renomeada');
    await edit.getByRole('button',{name:'Salvar produto',exact:true}).click(); await edit.waitForURL('**/produtos'); await edit.close();
    await page.waitForFunction(() => !document.getElementById('review-fields').disabled);
    await page.locator('#review-save').click();
    await page.waitForFunction(() => document.getElementById('review-status').textContent.includes('catálogo ou o estoque mudou'));
    check(await page.locator('#review-save').isHidden() && await page.locator('#ai-total').innerText() === 'R$ 40,20', 'Preço alterado atualiza prévia e exige nova confirmação');
    check(await page.locator('#ai-items').innerText().then(t=>t.includes('Caneca QA renomeada')), 'Nome alterado atualizado na conferência');
    check(await stock() === 10, 'Tentativa com catálogo alterado não baixa estoque');
    await page.locator('#review-confirm').click(); await page.locator('#review-save').waitFor({state:'visible'});
    await page.locator('#review-save').click(); await page.waitForURL('**/pedidos/detalhes?id=*');
    const secondId = new URL(page.url()).searchParams.get('id');
    check(secondId !== firstId && await page.locator('#saved-order-total').innerText() === 'R$ 40,20', 'Reconfirmação salva com valores revisados');
    await page.getByRole('button',{name:'Iniciar processamento',exact:true}).click();
    await page.getByRole('button',{name:'Concluir pedido',exact:true}).click();
    await page.waitForFunction(() => document.getElementById('saved-order-status')?.textContent === 'Concluído');
    check((await statusPost(secondId,'cancelled',{confirm:'1'})).status() === 409 && await stock() === 8, 'Concluído não pode cancelar e mantém baixa original');
    await page.goto(firstUrl);
    check(await page.locator('#saved-order-total').innerText() === 'R$ 37,05' && await page.locator('tbody').innerText().then(t=>t.includes('Caneca QA Etapa 05')), 'Detalhes mantêm preço e nome antigos');
    for (const route of ['/pedidos',`/pedidos/detalhes?id=${firstId}`]) {
      await page.goto(base+route);
      for (const width of [390,320]) {
        await page.setViewportSize({width,height:844});
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Layout responsivo ${route} em ${width}px`);
      }
    }
    await page.screenshot({path:'.qa/orders-stage05-mobile.png',fullPage:true});
    await page.setViewportSize({width:1366,height:900}); await page.screenshot({path:'.qa/orders-stage05-desktop.png',fullPage:true});
    check(calls === 1 && errors.length === 0, `IA simulada uma vez e nenhum erro JavaScript: ${errors.join(',')}`);
    console.log(`OK: ${checks} verificações HTTP/navegador da Etapa 05, sem API real.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
