<?php use App\Core\Session;
?>
<div class="page-heading">
    <div><div class="eyebrow">DO TEXTO À PRÉVIA</div><h1>Assistente Inteligente<span class="text-accent">.</span></h1><p>Descreva seu pedido naturalmente. A inteligência artificial identificará os produtos, quantidades e variações disponíveis no catálogo.</p></div>
    <span class="stage-pill"><?= icon('check') ?> Etapa 04 · Conferência</span>
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
    <p class="small text-secondary mt-3 mb-0">Seu texto e os nomes dos produtos e variações ativos serão enviados ao Google Gemini para interpretação. Evite incluir dados pessoais.</p>
</section>

<div id="ai-error" class="alert alert-danger" role="alert" hidden></div>
<section id="ai-result" class="ai-panel" aria-labelledby="result-title"
    data-endpoint="<?= e(url('api/orders/validate')) ?>" data-catalog="<?= e(url('api/orders/catalog')) ?>"
    data-revision="<?= e(\App\Services\OrderPreviewSession::revision($_SESSION)) ?>"
    data-preview="<?= e(json_encode($_SESSION['confirmed_order_preview'] ?? null, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>">
    <h2 id="result-title" class="h5" tabindex="-1">Confira seu pedido</h2>
    <p class="text-secondary">Revise os itens identificados ou adicione produtos manualmente. Esta prévia não salva pedidos, não reserva produtos e não altera o estoque.</p>
    <div id="review-status" class="alert alert-info" role="status">Adicione produtos ou interprete uma mensagem para começar.</div>
    <div id="ai-warnings"></div>
    <form id="review-form" novalidate>
    <fieldset id="review-fields">
    <button type="button" class="btn btn-outline-primary mb-3" id="add-product">Adicionar produto</button>
    <div class="table-responsive">
        <table class="table align-middle ai-table">
            <caption class="visually-hidden">Itens interpretados e conferidos no catálogo</caption>
            <thead><tr><th scope="col">Produto</th><th scope="col">Variação</th><th scope="col">Quantidade</th><th scope="col">Preço unitário</th><th scope="col">Subtotal</th><th scope="col">Estoque</th><th scope="col">Situação</th><th scope="col">Ações</th></tr></thead>
            <tbody id="ai-items"></tbody>
        </table>
    </div>
    <p class="h5 mt-3"><span id="ai-total-label">Total estimado</span>: <span id="ai-total"></span></p>
    <p>Quantidade total: <strong id="review-units">0</strong> unidades</p>
    <div class="row g-3 mt-2">
        <div class="col-md-7"><label for="ai-notes" class="form-label">Observações do pedido</label><textarea id="ai-notes" class="form-control" rows="3" maxlength="2000" aria-describedby="notes-error"></textarea><div id="notes-error" class="text-danger small"></div></div>
        <div class="col-md-5"><label for="ai-deadline" class="form-label">Prazo solicitado pelo cliente</label><input id="ai-deadline" class="form-control" maxlength="200" aria-describedby="deadline-help deadline-error"><div id="deadline-help" class="form-text">Preferência do cliente, sem garantia de entrega. Expressões como “sexta-feira” são preservadas.</div><div id="deadline-error" class="text-danger small"></div></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-4"><button type="button" id="review-validate" class="btn btn-outline-primary">Revalidar prévia</button><button type="submit" id="review-confirm" class="btn btn-accent">Confirmar dados</button></div>
    </fieldset>
    </form>
</section>

<div class="modal fade" id="product-picker" tabindex="-1" aria-labelledby="picker-title" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h2 id="picker-title" class="modal-title h5">Selecionar produto</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <form id="picker-form"><div class="modal-body">
            <label for="product-search" class="form-label">Pesquisar por nome</label><input id="product-search" type="search" class="form-control mb-3" maxlength="150">
            <label for="picker-product" class="form-label">Produto ativo</label><select id="picker-product" class="form-select mb-3" required></select>
            <div class="d-flex gap-2 align-items-center mb-3"><button type="button" id="picker-prev" class="btn btn-sm btn-outline-secondary">Anterior</button><span id="picker-page" class="small"></span><button type="button" id="picker-next" class="btn btn-sm btn-outline-secondary">Próxima</button></div>
            <label for="picker-variant" class="form-label">Variação</label><select id="picker-variant" class="form-select mb-3"></select>
            <label for="picker-quantity" class="form-label">Quantidade</label><input id="picker-quantity" type="number" min="1" max="10000" step="1" value="1" required class="form-control">
            <p id="picker-info" class="small mt-2"></p><p id="picker-error" class="text-danger" role="alert"></p>
        </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" id="picker-submit" class="btn btn-accent">Aplicar produto</button></div></form>
    </div></div>
</div>
