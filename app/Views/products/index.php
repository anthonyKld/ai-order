<?php
use App\Core\Session;
$token = Session::token();
$pageUrl = static fn (int $number): string => url('produtos') . '?' . http_build_query(['q' => $search, 'status' => $statusFilter, 'page' => $number]);
?>
<div class="page-heading">
    <div><div class="eyebrow">SEU CATÁLOGO</div><h1>Produtos<span class="text-accent">.</span></h1><p>Organize preços, estoque e variações em um só lugar.</p></div>
    <a class="btn btn-accent" href="<?= e(url('produtos/novo')) ?>">+ Novo produto</a>
</div>
<?php if ($flash !== null): ?><div class="alert alert-success" role="status"><?= e($flash) ?></div><?php endif; ?>
<section class="catalog-panel" aria-label="Catálogo de produtos">
    <form method="get" action="<?= e(url('produtos')) ?>" class="row g-3 align-items-end catalog-filters">
        <div class="col-md-6"><label class="form-label" for="search">Pesquisar por nome</label><input class="form-control" id="search" name="q" type="search" maxlength="150" value="<?= e($search) ?>" placeholder="Ex.: Camiseta Básica"></div>
        <div class="col-md-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">Todos os status</option><option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Ativo</option><option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Inativo</option></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-catalog" type="submit">Filtrar</button><a class="btn btn-outline-secondary" href="<?= e(url('produtos')) ?>">Limpar</a></div>
    </form>
    <?php if ($products === []): ?>
    <div class="catalog-empty"><span class="empty-icon"><?= icon('box') ?></span><h2><?= $search !== '' || $statusFilter !== '' ? 'Nenhum produto encontrado' : 'Seu catálogo começa aqui' ?></h2><p><?= $search !== '' || $statusFilter !== '' ? 'Tente outro nome ou ajuste o filtro de status.' : 'Cadastre o primeiro produto e mantenha seu estoque organizado.' ?></p><a class="btn btn-accent" href="<?= e(url('produtos/novo')) ?>"><?= $search !== '' || $statusFilter !== '' ? 'Novo produto' : 'Cadastrar primeiro produto' ?></a></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle catalog-table mb-0">
            <caption class="visually-hidden">Produtos cadastrados, preços, estoque e ações</caption>
            <thead><tr><th scope="col">ID</th><th scope="col">Produto</th><th scope="col">Preço</th><th scope="col">Estoque</th><th scope="col">Status</th><th scope="col">Cadastro</th><th scope="col" class="text-end">Ações</th></tr></thead>
            <tbody><?php foreach ($products as $product): ?>
                <tr><td class="text-secondary">#<?= e($product['id']) ?></td><td class="catalog-name"><strong><?= e($product['name']) ?></strong><small><?= (int) $product['variant_count'] > 0 ? e($product['variant_count']) . ' variações' : 'Produto simples' ?></small></td><td class="text-nowrap"><?= e(money($product['price'])) ?></td><td><?= e($product['available_stock']) ?> <span class="text-secondary">un.</span></td><td><span class="badge <?= $product['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $product['active'] ? 'Ativo' : 'Inativo' ?></span></td><td class="text-nowrap"><?= e(date('d/m/Y', strtotime($product['created_at']))) ?></td><td><div class="d-flex justify-content-end gap-2"><a class="btn btn-sm btn-outline-secondary" aria-label="Editar <?= e($product['name']) ?>" href="<?= e(url('produtos/editar') . '?id=' . $product['id']) ?>">Editar</a><button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-product" data-product-id="<?= e($product['id']) ?>" data-product-name="<?= e($product['name']) ?>" aria-label="Excluir <?= e($product['name']) ?>">Excluir</button></div></td></tr>
            <?php endforeach; ?></tbody>
        </table>
    </div>
    <div class="catalog-pagination"><p class="mb-0 text-secondary"><?= e($total) ?> produto(s) · Página <?= e($page) ?> de <?= e($pages) ?></p>
        <nav aria-label="Paginação de produtos"><ul class="pagination pagination-sm mb-0">
            <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="<?= e($pageUrl($page - 1)) ?>">Anterior</a></li><?php endif; ?>
            <?php for ($number = max(1, $page - 2); $number <= min($pages, $page + 2); $number++): ?><li class="page-item <?= $number === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($pageUrl($number)) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= e($number) ?></a></li><?php endfor; ?>
            <?php if ($page < $pages): ?><li class="page-item"><a class="page-link" href="<?= e($pageUrl($page + 1)) ?>">Próxima</a></li><?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</section>
<div class="modal fade" id="delete-product" tabindex="-1" aria-labelledby="delete-title" aria-describedby="delete-description" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title fs-5" id="delete-title">Excluir produto?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body" id="delete-description">O produto <strong id="delete-product-name"></strong> e suas variações serão removidos. Esta ação não pode ser desfeita.</div>
    <form method="post" action="<?= e(url('produtos/excluir')) ?>"><input type="hidden" name="_token" value="<?= e($token) ?>"><input type="hidden" name="id" id="delete-product-id"><input type="hidden" name="confirm" value="1"><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger" type="submit">Confirmar exclusão</button></div></form>
</div></div></div>
