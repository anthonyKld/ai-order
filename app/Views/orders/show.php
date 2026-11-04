<?php use App\Models\Order; use App\Core\Session; $token = Session::token(); ?>
<div class="page-heading"><div><div class="eyebrow">PEDIDO SALVO</div><h1>Detalhes do pedido<span class="text-accent">.</span></h1><p class="order-number" id="order-number"><?= e($order['order_number']) ?></p></div><a class="btn btn-outline-primary" href="<?= e(url('pedidos')) ?>">Voltar aos pedidos</a></div>
<?php if ($flash): ?><div class="alert alert-success" role="status"><?= e($flash) ?></div><?php endif; ?>
<section class="ai-panel">
    <div class="d-flex flex-wrap gap-3 justify-content-between mb-4"><span id="saved-order-status" class="badge align-self-start text-bg-<?= e(Order::COLORS[$order['status']]) ?>"><?= e(Order::STATUSES[$order['status']]) ?></span><span>Criado em <?= e(date('d/m/Y H:i:s', strtotime($order['created_at']))) ?></span></div>
    <div class="table-responsive"><table class="table align-middle orders-table"><caption class="visually-hidden">Produtos e valores registrados na criação</caption>
        <thead><tr><th>Produto</th><th>Variação</th><th>Quantidade</th><th>Preço unitário</th><th>Subtotal</th></tr></thead>
        <tbody><?php foreach ($order['items'] as $item): ?><tr><td><?= e($item['product_name']) ?></td><td><?= e($item['variant_name'] ?? 'Sem variação') ?></td><td><?= e($item['quantity']) ?></td><td class="text-nowrap"><?= e(money($item['unit_price'])) ?></td><td class="text-nowrap"><?= e(money($item['subtotal'])) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
    <p class="h4 mt-3">Total: <span id="saved-order-total"><?= e(money($order['total'])) ?></span></p><p><?= e($order['total_items']) ?> unidades</p>
    <dl class="row mt-4"><dt class="col-md-3">Observações</dt><dd class="col-md-9 order-text"><?= e($order['notes'] ?? 'Sem observações.') ?></dd><dt class="col-md-3">Prazo solicitado</dt><dd class="col-md-9 order-text"><?= e($order['requested_deadline'] ?? 'Não informado.') ?></dd>
        <?php if ($order['cancelled_at']): ?><dt class="col-md-3">Cancelado em</dt><dd class="col-md-9"><?= e(date('d/m/Y H:i:s', strtotime($order['cancelled_at']))) ?></dd><?php endif; ?></dl>
    <div class="d-flex flex-wrap gap-2 mt-4">
    <?php foreach (Order::TRANSITIONS[$order['status']] as $next): ?>
        <?php if ($next === 'cancelled'): ?><button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#cancel-order">Cancelar pedido</button>
        <?php else: ?><form method="post" action="<?= e(url('pedidos/status')) ?>" data-order-action><input type="hidden" name="_token" value="<?= e($token) ?>"><input type="hidden" name="id" value="<?= e($order['id']) ?>"><input type="hidden" name="status" value="<?= e($next) ?>"><button class="btn btn-accent"><?= $next === 'processing' ? 'Iniciar processamento' : 'Concluir pedido' ?></button></form><?php endif; ?>
    <?php endforeach; ?>
    </div>
    <?php if (Order::TRANSITIONS[$order['status']] === []): ?><p class="text-secondary">Este pedido está encerrado e permanece disponível no histórico.</p><?php endif; ?>
</section>
<?php if (in_array('cancelled', Order::TRANSITIONS[$order['status']], true)): ?>
<div class="modal fade" id="cancel-order" tabindex="-1" aria-labelledby="cancel-order-title" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h2 id="cancel-order-title" class="modal-title h5">Cancelar pedido?</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body">Os itens serão devolvidos ao estoque. O pedido continuará no histórico e não poderá ser reaberto.</div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Voltar</button><form method="post" action="<?= e(url('pedidos/status')) ?>" data-order-action><input type="hidden" name="_token" value="<?= e($token) ?>"><input type="hidden" name="id" value="<?= e($order['id']) ?>"><input type="hidden" name="status" value="cancelled"><input type="hidden" name="confirm" value="1"><button class="btn btn-danger">Confirmar cancelamento</button></form></div>
</div></div></div>
<?php endif; ?>
