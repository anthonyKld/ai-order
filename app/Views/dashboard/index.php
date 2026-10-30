<div class="page-heading">
    <div><div class="eyebrow">VISÃO GERAL</div><h1>Seu próximo pedido começa aqui<span class="text-accent">.</span></h1><p>Bem-vindo ao AI Order. Estamos preparando um jeito mais simples de gerenciar pedidos.</p></div>
    <span class="stage-pill"><span class="status-dot"></span> Etapa 02 · Produtos</span>
</div>

<section class="welcome-panel" aria-labelledby="welcome-title">
    <div class="welcome-copy">
        <span class="hero-label"><?= icon('spark') ?> INTELIGÊNCIA A SERVIÇO DO SEU NEGÓCIO</span>
        <h2 id="welcome-title">De uma conversa<br>para um pedido.<br><span>Simples assim.</span></h2>
        <p>Transforme mensagens do dia a dia em pedidos organizados. Uma nova forma de trabalhar, com você no controle de cada detalhe.</p>
        <a class="btn btn-accent" href="<?= e(url('assistente')) ?>"><?= icon('spark') ?> Conhecer o Assistente IA <?= icon('arrow') ?></a>
        <span class="hero-footnote">Funcionalidade planejada para a etapa 03</span>
    </div>
    <div class="workflow-preview" aria-label="Exemplo ilustrativo de um futuro pedido, sem processamento de IA">
        <div class="preview-caption"><span class="preview-dot"></span> DO TEXTO À ORGANIZAÇÃO <span>EXEMPLO ILUSTRATIVO</span></div>
        <div class="message-preview"><span class="message-avatar"><?= icon('chat') ?></span><div><span class="message-author">Uma mensagem sua</span><p>“Quero 3 camisetas pretas tamanho M para sexta-feira.”</p></div></div>
        <div class="connection-line"><span><?= icon('spark') ?> Interpretação com IA <small>em breve</small></span></div>
        <div class="order-preview">
            <div class="order-preview-header"><span><?= icon('orders') ?> Pedido estruturado</span><span class="review-tag">Para conferência</span></div>
            <div class="product-preview"><span class="product-image"><svg width="38" height="38" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="m16 8-11 7 6 10 6-3v19h14V22l6 3 6-10-11-7c-2 7-14 7-16 0Z" fill="#353c39" stroke="#171f1b" stroke-width="2"/><path d="M16 8c2 7 14 7 16 0" stroke="#919a94" stroke-width="2"/></svg></span><span><strong>Camiseta básica</strong><small>Preta <span>·</span> Tamanho M</small></span><span class="quantity-tag">3 un.</span></div>
            <div class="order-preview-footer"><span><?= icon('clock') ?> Prazo solicitado</span><strong>Sexta-feira</strong></div>
        </div>
        <p class="preview-disclaimer"><?= icon('shield') ?> Você confere e confirma antes de salvar.</p>
    </div>
</section>

<section class="features-section" aria-labelledby="features-title">
    <div class="section-heading"><div><h2 id="features-title">Tudo conectado, do início ao fim</h2><p>Conheça os módulos que vão fazer parte do seu dia a dia.</p></div><span class="quiet-label">MÓDULOS DO SISTEMA</span></div>
    <div class="row g-3">
        <?php foreach ([['box', 'produtos', 'Catálogo de produtos', 'Organize produtos, preços, estoque e variações em um único lugar.', 'Etapa 02'], ['spark', 'assistente', 'Assistente com IA', 'Escreva seu pedido naturalmente e receba os dados prontos para conferir.', 'Etapa 03'], ['orders', 'pedidos', 'Gestão de pedidos', 'Acompanhe os detalhes e o status de cada pedido com clareza.', 'Etapa 05']] as [$symbol, $path, $name, $description, $step]): ?>
        <div class="col-md-4"><a class="feature-card" href="<?= e(url($path)) ?>"><div class="feature-top"><span class="feature-icon <?= $symbol === 'spark' ? 'purple' : '' ?>"><?= icon($symbol) ?></span><span class="coming-badge"><?= $path === 'produtos' ? 'Disponível' : 'Em breve' ?></span></div><h3><?= e($name) ?></h3><p><?= e($description) ?></p><div class="feature-bottom"><span><?= e($step) ?></span><?= icon('arrow') ?></div></a></div>
        <?php endforeach; ?>
    </div>
</section>

<section class="development-panel" aria-labelledby="development-title">
    <div class="development-intro"><span class="feature-icon small"><?= icon('code') ?></span><div><h2 id="development-title">Organizando o catálogo</h2><p>Uma etapa de cada vez, com propósito.</p></div><span class="current-badge">ETAPA ATUAL</span></div>
    <div class="development-details"><div><strong>Gerenciamento de produtos</strong><p>Cadastro, preços, estoque e variações disponíveis.</p></div><span class="foundation-tag"><?= icon('check') ?> Você está aqui</span></div>
    <div class="roadmap" aria-label="Etapas do projeto">
        <?php foreach (['Estrutura', 'Produtos', 'Integração IA', 'Validação', 'Pedidos', 'Portfólio'] as $index => $step): ?>
        <div class="roadmap-step <?= $index === 1 ? 'current' : '' ?>"><span class="step-node"><?= $index === 0 ? icon('check') : e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span><span><?= e($step) ?></span></div>
        <?php endforeach; ?>
    </div>
</section>
