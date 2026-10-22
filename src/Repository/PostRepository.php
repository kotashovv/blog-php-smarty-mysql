<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Post;
use PDO;

final class PostRepository
{
    private const SORTS = ['date' => 'p.published_at', 'views' => 'p.views'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int, list<Post>> */
    public function latestByCategory(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ranked.* FROM (
                SELECT p.*, pc.category_id,
                       ROW_NUMBER() OVER (
                           PARTITION BY pc.category_id ORDER BY p.published_at DESC, p.id DESC
                       ) AS position_in_category
                FROM posts p JOIN post_category pc ON pc.post_id = p.id
             ) ranked WHERE position_in_category <= 3
             ORDER BY category_id, position_in_category'
        );
        $statement->execute();
        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $result[(int) $row['category_id']][] = Post::fromRow($row);
        }

        return $result;
    }

    public function countInCategory(int $categoryId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM post_category WHERE category_id = :category_id');
        $statement->execute(['category_id' => $categoryId]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<Post> */
    public function inCategory(int $categoryId, string $sort, string $direction, int $limit, int $offset): array
    {
        // Column and direction come from whitelists only: identifiers cannot be bound as parameters.
        $order = self::SORTS[$sort] ?? self::SORTS['date'];
        $dir = $direction === 'asc' ? 'ASC' : 'DESC';
        $statement = $this->pdo->prepare(
            'SELECT p.* FROM posts p JOIN post_category pc ON pc.post_id = p.id
             WHERE pc.category_id = :category_id
             ORDER BY ' . $order . ' ' . $dir . ', p.id ' . $dir . ' LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('category_id', $categoryId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(Post::fromRow(...), $statement->fetchAll());
    }

    public function findBySlug(string $slug): ?Post
    {
        $statement = $this->pdo->prepare('SELECT * FROM posts WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return $row === false ? null : Post::fromRow($row);
    }

    public function incrementViews(int $postId): void
    {
        $statement = $this->pdo->prepare('UPDATE posts SET views = views + 1 WHERE id = :id');
        $statement->execute(['id' => $postId]);
    }

    /** @return list<Post> */
    public function related(int $postId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.*, COUNT(shared.category_id) AS common_categories
             FROM posts p
             LEFT JOIN post_category candidate ON candidate.post_id = p.id
             LEFT JOIN post_category shared
               ON shared.category_id = candidate.category_id AND shared.post_id = :source_id
             WHERE p.id <> :excluded_id
             GROUP BY p.id
             ORDER BY common_categories DESC, p.published_at DESC, p.id DESC
             LIMIT 3'
        );
        $statement->execute(['source_id' => $postId, 'excluded_id' => $postId]);

        return array_map(Post::fromRow(...), $statement->fetchAll());
    }
}
