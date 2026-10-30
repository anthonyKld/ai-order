<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly array $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }
        // Impede que valores de configuração injetem parâmetros extras no DSN.
        foreach (['host', 'database'] as $key) {
            if (preg_match('/[;\x00-\x1F]/', (string) $this->config[$key])) {
                throw new RuntimeException('Configuração de banco inválida.');
            }
        }
        $port = filter_var($this->config['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            throw new RuntimeException('Configuração de banco inválida.');
        }
        try {
            $this->connection = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $this->config['host'], $port, $this->config['database']),
                $this->config['username'],
                $this->config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
            );
        } catch (PDOException) {
            error_log('AI Order: conexão MySQL indisponível.');
            throw new RuntimeException('Não foi possível conectar ao banco de dados.');
        }
        return $this->connection;
    }
}
