<?php

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Controllers\PlaceholderController;

$router->get('/', [new DashboardController(), 'index']);
$router->get('/produtos', fn () => (new PlaceholderController())->show('produtos'));
$router->get('/pedidos', fn () => (new PlaceholderController())->show('pedidos'));
$router->get('/assistente', fn () => (new PlaceholderController())->show('assistente'));
// O roteador já suporta post(); operações de escrita pertencem às próximas etapas.
