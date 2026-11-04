<?php use App\Models\Order; ?>
<div class="page-heading">
    <div><div class="eyebrow">HISTÓRICO COMERCIAL</div><h1>Pedidos<span class="text-accent">.</span></h1><p>Acompanhe os pedidos salvos e gerencie seu andamento.</p></div>
    <a class="btn btn-accent" href="<?= e(url('assistente')) ?>">Novo pedido</a>
</div>
<section class="ai-panel">
    <form method="get" action="<?= e(url('pedidos')) ?>" class="row g-3 align-items-end mb-4">
        <div class="col-md-6"><label for="order-search" class="form-label">Número do pedido</label><input class="form-control" type="search" id="order-search" name="q" maxlength="200" value="<?= e($search) ?>"></div>
        <div class="col-md-4"><label for="order-status" class="form-label">Status</label><select class="form-select" id="order-status" name="status"><option value="">Todos os status</option><?php foreach (Order::STATUSES as $key => $label): ?><option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Pesquisar</button></div>
    </form>
    <?php if (!$orders): ?><p class="text-secondary mb-0">Nenhum pedido encontrado. Crie um pedido no Assistente IA ou ajuste os filtros.</p><?php else: ?>
    <div class="table-responsive"><table class="table align-middle orders-table"><caption><?= e($total) ?> pedido(s) encontrado(s)</caption>
        <thead><tr><th>Número</th><th>Criação</th><th>Unidades</th><th>Total</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody><?php foreach ($orders as $order): ?><tr>
            <td class="order-number"><?= e($order['order_number']) ?></td><td class="text-nowrap"><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
            <td><?= e($order['total_items']) ?></td><td class="text-nowrap"><?= e(money($order['total'])) ?></td>
            <td><span class="badge text-bg-<?= e(Order::COLORS[$order['status']]) ?>"><?= e(Order::STATUSES[$order['status']]) ?></span></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('pedidos/detalhes?id=' . $order['id'])) ?>">Detalhes</a></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
    <nav class="d-flex flex-wrap gap-3 align-items-center mt-3" aria-label="Paginação de pedidos">
        <?php if ($page > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('pedidos?' . http_build_query(['q' => $search, 'status' => $statusFilter, 'page' => $page - 1]))) ?>">Anterior</a><?php endif; ?>
        <span>Página <?= e($page) ?> de <?= e($pages) ?></span>
        <?php if ($page < $pages): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('pedidos?' . http_build_query(['q' => $search, 'status' => $statusFilter, 'page' => $page + 1]))) ?>">Próxima</a><?php endif; ?>
    </nav>
</section>
