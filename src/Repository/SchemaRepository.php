<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use RuntimeException;

final class SchemaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function migrate(string $filename): int
    {
        $schema = file_get_contents($filename);
        if ($schema === false) {
            throw new RuntimeException('Cannot read the database schema.');
        }
        // The controlled initial schema contains plain CREATE TABLE statements only.
        // MySQL DDL commits implicitly; each statement is independently repeatable.
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($statements as $sql) {
            $this->pdo->prepare($sql)->execute();
        }

        return count($statements);
    }
}
