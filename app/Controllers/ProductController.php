<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Models\Product;
use App\Validation\ProductValidator;
use DomainException;

final class ProductController
{
    private function model(): Product
    {
        return new Product((new Database(require dirname(__DIR__, 2) . '/config/database.php'))->connection());
    }

    public function index(): void
    {
        $search = ProductValidator::text($_GET['q'] ?? '');
        $status = ProductValidator::text($_GET['status'] ?? '');
        $page = ProductValidator::integer($_GET['page'] ?? '1', 1) ?? 1;
        // Limita consultas desnecessariamente grandes recebidas por URL.
        $search = substr($search, 0, 600);
        $result = $this->model()->paginate($search, $status, $page);
        View::render('products/index', [...$result, 'title' => 'Produtos', 'active' => 'produtos',
            'search' => $search, 'statusFilter' => $status, 'flash' => Session::takeFlash(),
        ]);
    }

    public function create(): void
    {
        $this->form(['name' => '', 'description' => '', 'price' => '', 'stock' => '0', 'active' => '1', 'variants' => []]);
    }

    public function edit(): void
    {
        $id = ProductValidator::integer($_GET['id'] ?? null, 1, 4294967295);
        $product = $id === null ? null : $this->model()->find($id);
        if ($product === null) {
            $this->error(404);
            return;
        }
        $this->form($product, [], $id);
    }

    public function store(): void
    {
        $this->save(false);
    }

    public function update(): void
    {
        $this->save(true);
    }

    private function save(bool $editing): void
    {
        if (!$this->csrf()) {
            return;
        }
        $id = $editing ? ProductValidator::integer($_POST['id'] ?? null, 1, 4294967295) : null;
        if ($editing && $id === null) {
            $this->error(404);
            return;
        }
        $result = (new ProductValidator())->validate($_POST);
        if ($result['errors'] !== []) {
            http_response_code(422);
            $this->form($result['values'], $result['errors'], $id);
            return;
        }
        try {
            if ($this->model()->save($result['data'], $id) === null) {
                $this->error(404);
                return;
            }
        } catch (DomainException $exception) {
            http_response_code(422);
            $this->form($result['values'], ['product' => $exception->getMessage()], $id);
            return;
        }
        Session::flash($editing ? 'Produto atualizado com sucesso.' : 'Produto cadastrado com sucesso.');
        header('Location: ' . url('produtos'), true, 303);
    }

    public function delete(): void
    {
        if (!$this->csrf()) {
            return;
        }
        $id = ProductValidator::integer($_POST['id'] ?? null, 1, 4294967295);
        if ($id === null) {
            $this->error(404);
            return;
        }
        if (($_POST['confirm'] ?? null) !== '1') {
            $this->error(422, 'Confirme a exclusão antes de continuar.');
            return;
        }
        try {
            if (!$this->model()->delete($id)) {
                $this->error(404);
                return;
            }
        } catch (DomainException $exception) {
            $this->error(409, $exception->getMessage());
            return;
        }
        Session::flash('Produto excluído com sucesso.');
        header('Location: ' . url('produtos'), true, 303);
    }

    private function csrf(): bool
    {
        if (!Session::validToken($_POST['_token'] ?? null)) {
            $this->error(403, 'Sua sessão expirou ou o formulário é inválido. Reabra a página e tente novamente.');
            return false;
        }
        return true;
    }

    private function form(array $values, array $errors = [], ?int $id = null): void
    {
        View::render('products/form', ['title' => $id === null ? 'Novo produto' : 'Editar produto',
            'active' => 'produtos', 'values' => $values, 'errors' => $errors, 'id' => $id,
        ]);
    }

    private function error(int $status, ?string $message = null): void
    {
        http_response_code($status);
        View::render('errors/http', ['title' => $status === 404 ? 'Produto não encontrado' : 'Não foi possível concluir',
            'active' => 'produtos', 'status' => $status, 'message' => $message,
        ]);
    }
}
