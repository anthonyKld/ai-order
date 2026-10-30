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
$router->get('/pedidos', fn () => (new PlaceholderController())->show('pedidos'));
$assistant = new AIController();
$router->get('/assistente', [$assistant, 'index']);
$router->post('/api/ai/interpret', [$assistant, 'interpret']);
