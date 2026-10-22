<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use Throwable;

final class SeedRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param list<array<string, mixed>> $categories
     * @param list<array<string, mixed>> $posts
     * @return array<string, int>
     */
    public function save(array $categories, array $posts, bool $fresh): array
    {
        $this->pdo->beginTransaction();
        try {
            if ($fresh) {
                foreach (['DELETE FROM post_category', 'DELETE FROM posts', 'DELETE FROM categories'] as $sql) {
                    $this->pdo->prepare($sql)->execute();
                }
            }
            $categoryInsert = $this->pdo->prepare(
                'INSERT INTO categories (slug, name, description, created_at)
                 VALUES (:slug, :name, :description, :created_at)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $ids = [];
            foreach ($categories as $category) {
                $categoryInsert->execute($category);
                $ids[$category['slug']] = (int) $this->pdo->lastInsertId();
            }
            $postInsert = $this->pdo->prepare(
                'INSERT INTO posts (slug, title, description, content, image, views, published_at, created_at)
                 VALUES (:slug, :title, :description, :content, :image, :views, :published_at, :created_at)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $linkInsert = $this->pdo->prepare(
                'INSERT INTO post_category (post_id, category_id) VALUES (:post_id, :category_id)
                 ON DUPLICATE KEY UPDATE post_id = post_category.post_id'
            );
            foreach ($posts as $post) {
                $slugs = $post['categories'];
                unset($post['categories']);
                $postInsert->execute($post);
                $postId = (int) $this->pdo->lastInsertId();
                foreach ($slugs as $slug) {
                    $linkInsert->execute(['post_id' => $postId, 'category_id' => $ids[$slug]]);
                }
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        $statement = $this->pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM categories) AS categories,
                    (SELECT COUNT(*) FROM posts) AS posts,
                    (SELECT COUNT(*) FROM post_category) AS links'
        );
        $statement->execute();

        return array_map('intval', $statement->fetch());
    }
}
