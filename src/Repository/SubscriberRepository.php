<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class SubscriberRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Idempotent: a repeated address is accepted without revealing that it already exists. */
    public function add(string $email): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO subscribers (email) VALUES (:email) ON DUPLICATE KEY UPDATE email = email'
        );
        $statement->execute(['email' => $email]);
    }
}
