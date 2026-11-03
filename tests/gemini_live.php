<?php

declare(strict_types=1);

use App\Services\AIException;
use App\Services\AIService;
use App\Validation\OrderPreviewValidator;
use Dotenv\Dotenv;

// Optativo: uma chamada real ao Gemini, apenas com dados fictícios, sem banco.
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/ai.php';
$product = ['id'=>1, 'name'=>'Caneca Personalizada', 'active'=>1, 'price'=>'24.90', 'stock'=>10, 'variants'=>[]];
$catalog = [['id'=>1, 'name'=>$product['name'], 'variants'=>[]]];
try {
    $result = (new AIService($config))->interpret('Quero 2 canecas personalizadas.', $catalog);
    $preview = (new OrderPreviewValidator())->validate($result, $catalog, static fn (int $id) => $id === 1 ? $product : null);
    if (count($preview['items']) !== 1 || $preview['has_pending_items']
        || $preview['items'][0]['quantity'] !== 2 || $preview['estimated_total'] !== '49.80') {
        fwrite(STDERR, "FALHA: Gemini respondeu, mas a interpretação não passou na conferência local.\n");
        exit(1);
    }
    echo 'OK: Gemini (' . $config['model'] . ") interpretou 2 canecas; total local R$ 49,80. Sem gravações no banco.\n";
} catch (AIException $exception) {
    fwrite(STDERR, 'FALHA: ' . $exception->errorCode . ' — ' . $exception->getMessage() . "\n");
    exit(1);
}
