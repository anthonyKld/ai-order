<?php

declare(strict_types=1);

// Requer credenciais com CREATE/DROP DATABASE. Usa somente um banco aleatório próprio.
use App\Core\Database;
use App\Models\Product;
use App\Validation\ProductValidator;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/database.php';
$admin = (new Database($config))->connection();
$databaseName = 'ai_order_test_' . bin2hex(random_bytes(6));
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};
$admin->exec("CREATE DATABASE `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $db = (new Database([...$config, 'database' => $databaseName]))->connection();
    $migration = file_get_contents(dirname(__DIR__) . '/database/migrations/002_products.sql');
    $db->exec($migration);
    $model = new Product($db);
    $validator = new ProductValidator();
    $input = ['name' => 'Camiseta', 'price' => '39,90', 'stock' => '5', 'active' => '1'];
    $data = $validator->validate($input)['data'];
    $id = $model->save($data);
    $check($model->find($id)['price'] === '39.90', 'Preço deve persistir como decimal.');
    $db->exec($migration);
    $check($model->find($id) !== null, 'Reaplicar migration não pode apagar dados.');
    $variants = [['name' => 'Preta / P', 'stock' => '10'], ['name' => 'Preta / M', 'stock' => '15'], ['name' => 'Branca / G', 'stock' => '8']];
    $model->save($validator->validate([...$input, 'variants' => $variants])['data'], $id);
    $saved = $model->find($id);
    $check((int) $saved['stock'] === 0 && count($saved['variants']) === 3, 'Produto com variações não duplica estoque.');
    $check((int) $model->paginate('', '', 1)['products'][0]['available_stock'] === 33, 'Listagem deve somar variações.');
    $db->exec("UPDATE products SET updated_at = '2000-01-01 00:00:00' WHERE id = $id");
    $edit = $saved;
    $edit['name'] = 'Camiseta editada';
    $edit['variants'][0]['stock'] = 7;
    // O Model recebe tipos normalizados; inverte nomes para exercitar UNIQUE sem trocar IDs.
    [$edit['variants'][0]['name'], $edit['variants'][1]['name']] = [$edit['variants'][1]['name'], $edit['variants'][0]['name']];
    $model->save($edit, $id);
    $updated = $model->find($id);
    $check($updated['id'] === $saved['id'] && $updated['variants'][0]['id'] === $saved['variants'][0]['id'], 'Edição preserva IDs.');
    $check($updated['name'] === 'Camiseta editada' && (int) $updated['variants'][0]['stock'] === 7 && $updated['updated_at'] !== '2000-01-01 00:00:00', 'Edição deve atualizar dados e timestamp.');
    $duplicate = [...$data, 'variants' => [['id' => null, 'name' => 'Azul', 'stock' => 1], ['id' => null, 'name' => 'ÁZUL', 'stock' => 2]]];
    try {
        $model->save($duplicate, $id);
        $check(false, 'Banco deve impedir nomes duplicados.');
    } catch (DomainException) {
        $check($model->find($id) === $updated, 'Falha nas variações deve reverter toda a transação.');
    }
    $foreign = $model->save([...$data, 'name' => 'Outro produto']);
    try {
        $model->save($updated, $foreign);
        $check(false, 'ID de variação alheia deve falhar.');
    } catch (DomainException) {
        $check($model->find($foreign)['name'] === 'Outro produto', 'Variação de outro produto deve ser rejeitada sem gravar.');
    }
    for ($i = 0; $i < 12; $i++) $model->save([...$data, 'name' => 'Página ' . $i, 'active' => $i % 2]);
    $check($model->paginate('Página', '', 1)['total'] === 12 && count($model->paginate('Página', '', 1)['products']) === 10, 'Pesquisa e limite de página.');
    $check(count($model->paginate('Página', '', 2)['products']) === 2 && $model->paginate('Página', '', 999)['page'] === 2, 'Segunda página e página fora do limite.');
    $check($model->paginate('Página', '0', 1)['total'] === 6 && $model->paginate('Página', '1', 1)['total'] === 6, 'Filtro por status.');
    $check($model->paginate("' OR 1=1 --", '', 1)['total'] === 0, 'Pesquisa deve usar parâmetros.');
    $model->save([...$data, 'name' => '100%_literal!']);
    $check($model->paginate('%_', '', 1)['total'] === 1, 'Curingas devem ser pesquisados literalmente.');
    $model->save([...$updated, 'variants' => [], 'stock' => 9], $id);
    $check($model->find($id)['variants'] === [] && (int) $model->find($id)['stock'] === 9, 'Remover todas as variações permite estoque simples.');
    $model->save($validator->validate([...$input, 'variants' => $variants])['data'], $id);
    $beforeDelete = $model->find($id);
    // Simula exclusivamente no banco de teste os vínculos comerciais futuros.
    $db->exec('CREATE TABLE history_probe (product_id INT UNSIGNED, variant_id INT UNSIGNED, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT, FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT) ENGINE=InnoDB');
    $db->prepare('INSERT INTO history_probe (product_id) VALUES (?)')->execute([$id]);
    try {
        $model->delete($id);
        $check(false, 'Produto com histórico não pode ser excluído.');
    } catch (DomainException) {
        $check($model->find($id) === $beforeDelete, 'Exclusão bloqueada deve restaurar também as variações.');
    }
    $db->exec('DELETE FROM history_probe');
    $db->prepare('INSERT INTO history_probe (variant_id) VALUES (?)')->execute([$beforeDelete['variants'][0]['id']]);
    try {
        $model->save([...$data, 'variants' => []], $id);
        $check(false, 'Variação com histórico não pode ser removida.');
    } catch (DomainException) {
        $check($model->find($id) === $beforeDelete, 'Remoção de variação vinculada deve reverter a edição.');
    }
    $db->exec('DELETE FROM history_probe');
    $check($model->delete($id) && $model->find($id) === null, 'Exclusão deve remover o produto.');
    $check((int) $db->query("SELECT COUNT(*) FROM product_variants WHERE product_id = $id")->fetchColumn() === 0, 'Exclusão deve remover variações sem órfãos.');
    $check(!$model->delete($id) && $model->save($data, $id) === null, 'Produto ausente não deve ser recriado pela edição.');
    echo "OK: {$checks} verificações MySQL concluídas.\n";
} finally {
    // Nome gerado nesta execução, nunca DB_DATABASE nem um nome fornecido pelo usuário.
    $admin->exec("DROP DATABASE `$databaseName`");
}
