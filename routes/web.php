<?php

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Controllers\PlaceholderController;
use App\Controllers\ProductController;
use App\Controllers\AIController;

$router->get('/', [new DashboardController(), 'index']);
$products = new ProductController();
$router->get('/produtos', [$products, 'index']);
$router->get('/produtos/novo', [$products, 'create']);
$router->get('/produtos/editar', [$products, 'edit']);
$router->post('/produtos/criar', [$products, 'store']);
$router->post('/produtos/atualizar', [$products, 'update']);
$router->post('/produtos/excluir', [$products, 'delete']);
$orders = new \App\Controllers\OrderController();
$router->get('/pedidos', [$orders, 'index']);
$router->get('/pedidos/detalhes', [$orders, 'show']);
$router->post('/pedidos/status', [$orders, 'status']);
$router->post('/api/orders/save', [$orders, 'store']);
$assistant = new AIController();
$router->get('/assistente', [$assistant, 'index']);
$router->post('/api/ai/interpret', [$assistant, 'interpret']);
$preview = new \App\Controllers\OrderPreviewController();
$router->get('/api/orders/catalog', [$preview, 'catalog']);
$router->post('/api/orders/validate', [$preview, 'validate']);
