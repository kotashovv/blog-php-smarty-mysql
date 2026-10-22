<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Category;
use PDO;

final class CategoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<Category> */
    public function withPosts(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.* FROM categories c
             WHERE EXISTS (SELECT 1 FROM post_category pc WHERE pc.category_id = c.id)
             ORDER BY c.id'
        );
        $statement->execute();

        return array_map(Category::fromRow(...), $statement->fetchAll());
    }

    public function findBySlug(string $slug): ?Category
    {
        $statement = $this->pdo->prepare('SELECT * FROM categories WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return $row === false ? null : Category::fromRow($row);
    }

    /** @param list<int> $postIds @return array<int, list<Category>> */
    public function forPosts(array $postIds): array
    {
        $postIds = array_values(array_unique($postIds));
        if ($postIds === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($postIds), '?'));
        $statement = $this->pdo->prepare(
            'SELECT c.*, pc.post_id FROM categories c
             JOIN post_category pc ON pc.category_id = c.id
             WHERE pc.post_id IN (' . $placeholders . ') ORDER BY c.id'
        );
        $statement->execute($postIds);
        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $result[(int) $row['post_id']][] = Category::fromRow($row);
        }

        return $result;
    }
}
