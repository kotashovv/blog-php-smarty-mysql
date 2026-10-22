<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Repository\SeedRepository;
use App\Seeder\BlogSeeder;
use App\Seeder\SeedContent;
use App\Seeder\SeedImages;
use App\Seeder\SeedOptions;

$root = getenv('APP_ROOT') ?: dirname(__DIR__);
require $root . '/vendor/autoload.php';

function expectStageTwo(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rowsStageTwo(PDO $pdo, string $sql, array $parameters = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function snapshotStageTwo(PDO $pdo): array
{
    $data = [
        'categories' => rowsStageTwo($pdo, 'SELECT slug, name, description, created_at FROM categories ORDER BY slug'),
        'posts' => rowsStageTwo(
            $pdo,
            'SELECT slug, title, description, content, image, views, published_at, created_at FROM posts ORDER BY slug'
        ),
        'links' => rowsStageTwo(
            $pdo,
            'SELECT p.slug AS post_slug, c.slug AS category_slug FROM post_category pc
             JOIN posts p ON p.id = pc.post_id JOIN categories c ON c.id = pc.category_id
             ORDER BY p.slug, c.slug'
        ),
    ];

    return [
        'counts' => array_map('count', $data),
        'sha256' => hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
    ];
}

try {
    $arguments = array_slice($argv, 1);
    $snapshotOnly = in_array('--snapshot', $arguments, true);
    $exercise = in_array('--exercise', $arguments, true);
    $arguments = array_values(array_diff($arguments, ['--snapshot', '--exercise']));
    $options = SeedOptions::fromArguments($arguments);
    $pdo = (new Database(new Config($root)))->connection();
    $before = snapshotStageTwo($pdo);
    if ($snapshotOnly) {
        echo json_encode($before, JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);
    }
    expectStageTwo($before['counts']['categories'] === $options->categories, 'Unexpected category count');
    expectStageTwo($before['counts']['posts'] === $options->posts, 'Unexpected post count');
    $categoryCounts = rowsStageTwo(
        $pdo,
        'SELECT c.slug, COUNT(pc.post_id) AS posts FROM categories c
         LEFT JOIN post_category pc ON pc.category_id = c.id GROUP BY c.id, c.slug ORDER BY c.slug'
    );
    $postCounts = rowsStageTwo(
        $pdo,
        'SELECT p.id, COUNT(pc.category_id) AS categories FROM posts p
         LEFT JOIN post_category pc ON pc.post_id = p.id GROUP BY p.id ORDER BY p.id'
    );
    $empty = array_values(array_filter($categoryCounts, static fn (array $row): bool => (int) $row['posts'] === 0));
    $large = array_values(array_filter($categoryCounts, static fn (array $row): bool => (int) $row['posts'] > 10));
    $multi = count(array_filter($postCounts, static fn (array $row): bool => (int) $row['categories'] >= 2));
    $single = count(array_filter($postCounts, static fn (array $row): bool => (int) $row['categories'] === 1));
    expectStageTwo(in_array('editorial-notes', array_column($empty, 'slug'), true), 'Missing empty category');
    expectStageTwo(count($large) > 0, 'Missing category with >10 posts');
    expectStageTwo($multi / $options->posts >= 0.3 && $single > 0, 'Category distribution');
    expectStageTwo(min(array_column($postCounts, 'categories')) >= 1, 'Post without a category');
    expectStageTwo(max(array_column($postCounts, 'categories')) <= 3, 'More than three categories per post');
    $duplicates = rowsStageTwo(
        $pdo,
        'SELECT published_at, COUNT(*) AS posts FROM posts GROUP BY published_at HAVING COUNT(*) > 1'
    );
    expectStageTwo(count($duplicates) > 0, 'Missing equal publication dates');
    $range = rowsStageTwo(
        $pdo,
        'SELECT MIN(views) AS minimum_views, MAX(views) AS maximum_views,
                MIN(published_at) AS oldest, MAX(published_at) AS newest FROM posts'
    )[0];
    expectStageTwo((int) $range['minimum_views'] === 0 && (int) $range['maximum_views'] === 5000, 'Views range');
    expectStageTwo($range['oldest'] >= $options->date->modify('-12 months')->format('Y-m-d'), 'Old publication');
    expectStageTwo($range['newest'] < $options->date->format('Y-m-d'), 'Future publication');

    $titles = rowsStageTwo($pdo, 'SELECT COUNT(DISTINCT title) AS topics FROM posts')[0];
    expectStageTwo((int) $titles['topics'] >= min(25, $options->posts), 'Not enough distinct topics');
    expectStageTwo(max(array_column($categoryCounts, 'posts')) < $options->posts, 'One category contains every post');
    $tokens = json_decode(file_get_contents($root . '/design/tokens.json'), true, 512, JSON_THROW_ON_ERROR);
    $palette = array_values($tokens['colors']['tones']);
    $allowed = ['p', 'h2', 'h3', 'ul', 'ol', 'li', 'strong', 'em', 'a', 'blockquote'];
    $posts = rowsStageTwo($pdo, 'SELECT slug, content, image FROM posts ORDER BY slug');
    $imageHashes = [];
    foreach ($posts as $post) {
        preg_match_all('/<\/?([a-z][a-z0-9]*)\b([^>]*)>/i', $post['content'], $tags, PREG_SET_ORDER);
        foreach ($tags as $tag) {
            expectStageTwo(in_array(strtolower($tag[1]), $allowed, true), 'Disallowed article tag');
            $attributes = trim($tag[2]);
            expectStageTwo(
                $attributes === '' || ($tag[1] === 'a' && $attributes === 'href="/category/architecture"'),
                'Disallowed article attribute'
            );
        }
        expectStageTwo(preg_match('~^/uploads/seed/post-[0-9]+\.svg$~D', $post['image']) === 1, 'External image');
        $path = $root . '/public' . $post['image'];
        expectStageTwo(is_file($path), 'Missing local SVG');
        $svg = file_get_contents($path);
        expectStageTwo(!preg_match('/<script|<image|<foreignObject|\shref=|\son[a-z]+=/i', $svg), 'Unsafe SVG');
        preg_match_all('/fill="(#[0-9a-f]{6})"/i', $svg, $fills);
        expectStageTwo(count(array_diff($fills[1], $palette)) === 0, 'Image color outside tokens');
        $imageHashes[$post['image']] = hash('sha256', $svg);
    }

    $indexes = rowsStageTwo(
        $pdo,
        'SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'
    );
    foreach (['categories_slug_unique', 'posts_slug_unique', 'posts_published_at_id', 'posts_views_id', 'post_category_category_id'] as $index) {
        expectStageTwo(in_array($index, array_column($indexes, 'INDEX_NAME'), true), 'Missing index: ' . $index);
    }
    $foreignKeys = rowsStageTwo(
        $pdo,
        'SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table_name ORDER BY CONSTRAINT_NAME',
        ['table_name' => 'post_category']
    );
    expectStageTwo(count($foreignKeys) === 2, 'Missing foreign keys');
    expectStageTwo(array_unique(array_column($foreignKeys, 'DELETE_RULE')) === ['CASCADE'], 'Missing cascade rule');

    if ($exercise) {
        expectStageTwo(getenv('APP_ENV') === 'development', 'Destructive test requires APP_ENV=development');
        $content = new SeedContent();
        $categories = $content->categories($options);
        $images = new SeedImages($root);
        $repository = new SeedRepository($pdo);
        $seeder = new BlogSeeder($repository, $images, $content);
        $statement = $pdo->prepare('UPDATE posts SET views = :views, title = :title WHERE slug = :slug');
        $statement->execute(['views' => 7001, 'title' => 'Проверка сохранения правок', 'slug' => 'sustainable-systems']);
        $modified = snapshotStageTwo($pdo);
        $seeder->run($options);
        expectStageTwo(snapshotStageTwo($pdo) === $modified, 'Normal seed overwrote an existing post');

        // A failed --fresh must roll back its DELETE statements as well as inserts.
        $invalidPost = $content->post(1, $options, $categories);
        $invalidPost['title'] = str_repeat('X', 300);
        $invalidPost['image'] = '/uploads/seed/post-0001.svg';
        $failed = false;
        try {
            $repository->save($categories, [$invalidPost], true);
        } catch (PDOException) {
            $failed = true;
        }
        expectStageTwo($failed && snapshotStageTwo($pdo) === $modified, 'Failed fresh was not rolled back');
        file_put_contents($root . '/public/uploads/seed/post-999999999.svg', '<svg/>');
        file_put_contents($root . '/public/uploads/seed/keep-test.txt', 'preserve');
        $freshOptions = new SeedOptions($options->categories, $options->posts, true, $options->date);
        $seeder->run($freshOptions);
        expectStageTwo(snapshotStageTwo($pdo) === $before, 'Fresh seed is not deterministic');
        expectStageTwo(!is_file($root . '/public/uploads/seed/post-999999999.svg'), 'Obsolete SVG not removed');
        expectStageTwo(is_file($root . '/public/uploads/seed/keep-test.txt'), 'Unmanaged file removed');
        unlink($root . '/public/uploads/seed/keep-test.txt');

        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare('DELETE FROM posts WHERE slug = :slug');
            $delete->execute(['slug' => 'sustainable-systems']);
            $after = snapshotStageTwo($pdo);
            expectStageTwo($after['counts']['posts'] === $before['counts']['posts'] - 1, 'Post deletion');
            expectStageTwo($after['counts']['links'] < $before['counts']['links'], 'Post cascade failed');
        } finally {
            $pdo->rollBack();
        }
        expectStageTwo(snapshotStageTwo($pdo) === $before, 'Cascade probe changed the dataset');
        foreach ($imageHashes as $image => $hash) {
            expectStageTwo(hash_file('sha256', $root . '/public' . $image) === $hash, 'SVG is not deterministic');
        }
    }

    echo json_encode([
        'result' => 'OK',
        'seed_date' => $options->date->format('Y-m-d'),
        'snapshot' => snapshotStageTwo($pdo),
        'category_counts' => $categoryCounts,
        'distinct_topics' => (int) $titles['topics'],
        'multiple_categories' => $multi,
        'multiple_percent' => round(100 * $multi / $options->posts, 2),
        'single_category' => $single,
        'equal_date_groups' => count($duplicates),
        'range' => $range,
        'local_images' => count($imageHashes),
        'schema_indexes' => array_values(array_unique(array_column($indexes, 'INDEX_NAME'))),
        'foreign_keys' => $foreignKeys,
        'exercise' => $exercise ? 'preserve edits, rollback failed fresh, deterministic fresh, SVG cleanup, cascade: OK' : 'not requested',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    file_put_contents('php://stderr', 'Stage 2 check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
