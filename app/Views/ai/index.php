<?php use App\Core\Session;
// TODO(Gemini): atualizar o aviso de envio de dados abaixo quando o provedor for integrado.
// Não anunciar Gemini enquanto a aplicação ainda enviar as mensagens para a OpenAI.
?>
<div class="page-heading">
    <div><div class="eyebrow">DO TEXTO À PRÉVIA</div><h1>Assistente Inteligente<span class="text-accent">.</span></h1><p>Descreva seu pedido naturalmente. A inteligência artificial identificará os produtos, quantidades e variações disponíveis no catálogo.</p></div>
    <span class="stage-pill"><?= icon('spark') ?> Etapa 03 · Integração IA</span>
</div>

<section class="ai-panel mb-4" aria-labelledby="message-title">
    <h2 id="message-title" class="h5">O que você precisa?</h2>
    <form id="ai-form" action="<?= e(url('api/ai/interpret')) ?>" method="post" data-token="<?= e(Session::token()) ?>">
        <label class="form-label" for="ai-message">Descreva os produtos e as quantidades</label>
        <textarea id="ai-message" name="message" class="form-control" rows="4" maxlength="2000" required aria-describedby="message-help" placeholder="Preciso de 5 camisetas pretas M e 3 brancas G."></textarea>
        <div id="message-help" class="form-text">Inclua cor e tamanho quando houver variações. Até 2.000 caracteres.</div>
        <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
            <button type="submit" class="btn btn-accent" id="ai-submit"><?= icon('spark') ?> Interpretar pedido</button>
            <span id="ai-loading" role="status" hidden><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Interpretando seu pedido…</span>
        </div>
    </form>
    <noscript><p class="alert alert-warning mt-3">Ative o JavaScript para interpretar o pedido e visualizar a prévia.</p></noscript>
    <p class="small text-secondary mt-3 mb-0">Seu texto e os nomes dos produtos e variações ativos serão enviados à OpenAI para interpretação. Evite incluir dados pessoais.</p>
</section>

<div id="ai-error" class="alert alert-danger" role="alert" hidden></div>
<section id="ai-result" class="ai-panel" aria-labelledby="result-title" aria-live="polite" hidden>
    <h2 id="result-title" class="h5" tabindex="-1">Prévia gerada por IA e validada pelo sistema</h2>
    <p class="text-secondary">Confira os itens e as pendências. Esta prévia não salva pedidos, não reserva produtos e não altera o estoque.</p>
    <div id="ai-warnings"></div>
    <div class="table-responsive">
        <table class="table align-middle ai-table">
            <caption class="visually-hidden">Itens interpretados e conferidos no catálogo</caption>
            <thead><tr><th scope="col">Produto</th><th scope="col">Variação</th><th scope="col">Quantidade</th><th scope="col">Preço unitário</th><th scope="col">Subtotal</th><th scope="col">Situação</th></tr></thead>
            <tbody id="ai-items"></tbody>
        </table>
    </div>
    <p class="h5 mt-3"><span id="ai-total-label">Total estimado</span>: <span id="ai-total"></span></p>
    <dl class="mt-4 mb-0"><dt>Prazo mencionado</dt><dd id="ai-deadline"></dd><dt>Observações</dt><dd id="ai-notes"></dd></dl>
</section>
