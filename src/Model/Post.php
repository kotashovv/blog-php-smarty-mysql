<?php

declare(strict_types=1);

namespace App\Model;

final class Post
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $description,
        public readonly string $content,
        public readonly string $image,
        public readonly int $views,
        public readonly string $publishedAt
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['slug'],
            $row['title'],
            $row['description'],
            $row['content'],
            $row['image'],
            (int) $row['views'],
            $row['published_at']
        );
    }
}
