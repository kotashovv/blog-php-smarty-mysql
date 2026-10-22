<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config->string('DB_HOST', 'db'),
                $this->config->port('DB_PORT', 3306),
                $this->config->string('DB_DATABASE', 'bento')
            );
            $this->connection = new PDO(
                $dsn,
                $this->config->string('DB_USER', 'bento'),
                $this->config->string('DB_PASSWORD'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }

        return $this->connection;
    }
}
