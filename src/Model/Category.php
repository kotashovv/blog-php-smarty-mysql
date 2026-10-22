<?php

declare(strict_types=1);

namespace App\Model;

final class Category
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self((int) $row['id'], $row['slug'], $row['name'], $row['description']);
    }
}
