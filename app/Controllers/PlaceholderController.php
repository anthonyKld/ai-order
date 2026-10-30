<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class PlaceholderController
{
    public function show(string $page): void
    {
        $pages = [
            'pedidos' => ['title' => 'Pedidos', 'symbol' => 'orders', 'step' => '05', 'description' => 'Do primeiro pedido à entrega.', 'details' => 'A listagem, os detalhes e o acompanhamento de status estarão disponíveis após a implementação da validação de pedidos.'],
            'assistente' => ['title' => 'Assistente IA', 'symbol' => 'spark', 'step' => '03', 'description' => 'Escreva naturalmente. Organize com inteligência.', 'details' => 'A interpretação de mensagens em linguagem natural será implementada em uma etapa futura. Nenhuma API de inteligência artificial está conectada.'],
        ];
        View::render('pages/coming-soon', [...$pages[$page], 'active' => $page]);
    }
}
