<?php use App\Core\View; ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AI Order — Assistente Inteligente de Pedidos. Organize seu catálogo e prepare uma nova forma de receber pedidos.">
    <title><?= e($title) ?> · <?= e(View::config('name')) ?></title>
    <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
    <script src="<?= e(url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
    <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
    <?php if ($active === 'produtos'): ?><script src="<?= e(url('assets/js/products.js')) ?>" defer></script><?php endif; ?>
    <?php if ($active === 'assistente'): ?><script src="<?= e(url('assets/js/assistant.js')) ?>" defer></script><?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main">Pular para o conteúdo</a>
<aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebar-title">
    <div class="sidebar-top">
        <a class="brand" href="<?= e(url()) ?>" aria-label="AI Order — página inicial">
            <span class="brand-mark"><?= icon('spark') ?></span><span id="sidebar-title"><?= e(View::config('name')) ?><span class="brand-dot">.</span></span>
        </a>
        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Fechar menu"></button>
    </div>
    <div class="sidebar-body">
        <p class="nav-label">ESPAÇO DE TRABALHO</p>
        <nav aria-label="Menu principal" class="nav flex-column">
            <?php foreach ([['dashboard', '', 'grid', 'Dashboard'], ['produtos', 'produtos', 'box', 'Produtos'], ['pedidos', 'pedidos', 'orders', 'Pedidos'], ['assistente', 'assistente', 'spark', 'Assistente IA']] as [$key, $path, $symbol, $label]): ?>
            <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(url($path)) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
                <?= icon($symbol) ?><span><?= e($label) ?></span><?php if ($key === 'assistente'): ?><span class="mini-badge">IA</span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-note">
            <span class="small-icon"><?= icon('code') ?></span>
            <strong>Uma ideia em construção</strong>
            <p>Inteligência artificial e gestão comercial, conectadas.</p>
            <span class="version-tag">MVP · Etapa 03</span>
        </div>
        <div class="sidebar-footer"><span class="status-dot"></span> Ambiente de desenvolvimento</div>
    </div>
</aside>
<div class="app-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn menu-button d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Abrir menu"><?= icon('menu') ?></button>
            <span class="breadcrumb-parent">Workspace</span><span class="breadcrumb-divider">/</span><span class="breadcrumb-current"><?= e($title) ?></span>
        </div>
        <div class="header-identity"><span class="header-caption">Assistente Inteligente de Pedidos</span><span class="avatar" aria-hidden="true">AO</span></div>
    </header>
    <main id="main" class="main-content" tabindex="-1"><?= $content ?></main>
    <footer class="page-footer"><span><?= e(View::config('name')) ?> <span class="footer-dot">·</span> Menos tarefas. Mais possibilidades.</span><span>Versão 0.3.0 <span class="footer-dot">/</span> Integração com IA</span></footer>
</div>
</body>
</html>
