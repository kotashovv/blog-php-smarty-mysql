<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\NotFoundException;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Service\CategoryService;
use App\Service\HomeService;

$root = getenv('APP_ROOT') ?: dirname(__DIR__);
require $root . '/vendor/autoload.php';

final class QueryLog
{
    public array $queries = [];
}

final class MeasuredStatement extends PDOStatement
{
    private array $bindings = [];

    protected function __construct(private readonly QueryLog $log)
    {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bindings[$param] = [$value, $type];

        return parent::bindValue($param, $value, $type);
    }

    public function execute(?array $params = null): bool
    {
        $bindings = $this->bindings;
        foreach ($params ?? [] as $key => $value) {
            $bindings[is_int($key) ? $key + 1 : $key] = [$value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR];
        }
        $this->log->queries[] = ['sql' => $this->queryString, 'bindings' => $bindings];

        return parent::execute($params);
    }
}

function expectBlog(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function selectBlog(PDO $pdo, string $sql, array $params = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function explainBlog(PDO $pdo, array $query): array
{
    $statement = $pdo->prepare('EXPLAIN ' . $query['sql']);
    foreach ($query['bindings'] as $name => [$value, $type]) {
        $statement->bindValue($name, $value, $type);
    }
    $statement->execute();

    return ['sql' => $query['sql'], 'bindings' => $query['bindings'], 'plan' => $statement->fetchAll()];
}

try {
    $pdo = (new Database(new Config($root)))->connection();
    $log = new QueryLog();
    $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [MeasuredStatement::class, [$log]]);
    $categories = new CategoryRepository($pdo);
    $posts = new PostRepository($pdo);
    $homeService = new HomeService($categories, $posts);
    $categoryService = new CategoryService($categories, $posts);
    $home = $homeService->page();
    $homeQueries = $log->queries;
    expectBlog(count($homeQueries) === 3, 'Home must use exactly three queries');
    $evidence = ['home_query_count' => count($homeQueries), 'home' => [], 'category' => []];
    foreach ($home['categories'] as $section) {
        $expected = selectBlog(
            $pdo,
            'SELECT p.id FROM posts p JOIN post_category pc ON pc.post_id = p.id
             WHERE pc.category_id = :id ORDER BY p.published_at DESC, p.id DESC LIMIT 3',
            ['id' => $section['id']]
        );
        $ids = array_column($section['posts'], 'id');
        expectBlog($ids === array_map('intval', array_column($expected, 'id')), 'Home order differs from database');
        expectBlog(count($ids) >= 1 && count($ids) <= 3, 'Empty or oversized home section');
        $evidence['home'][] = ['id' => $section['id'], 'title' => $section['title'], 'posts' => $ids];
    }
    expectBlog(!in_array('Заметки редакции', array_column($home['categories'], 'title'), true), 'Empty section on home');

    // Add real nonempty categories inside a rolled-back transaction, then count actual execute() calls.
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare('INSERT INTO categories (slug, name, description) VALUES (:slug, :name, :description)');
        $link = $pdo->prepare('INSERT INTO post_category (post_id, category_id) VALUES (:post_id, :category_id)');
        $postId = $home['hero']['id'];
        for ($i = 0; $i < 20; $i++) {
            $insert->execute(['slug' => 'query-probe-' . $i, 'name' => 'Probe ' . $i, 'description' => 'Temporary']);
            $link->execute(['post_id' => $postId, 'category_id' => (int) $pdo->lastInsertId()]);
        }
        $log->queries = [];
        $expanded = $homeService->page();
        expectBlog(count($log->queries) === 3, 'Home query count grows with categories');
        expectBlog(count($expanded['categories']) === count($home['categories']) + 20, 'Expansion was not exercised');
        $evidence['expanded_home'] = ['sections' => count($expanded['categories']), 'query_count' => count($log->queries)];
    } finally {
        $pdo->rollBack();
    }

    foreach (['date' => 'p.published_at', 'views' => 'p.views'] as $sort => $column) {
        foreach ([1, 2, 3] as $page) {
            $model = $categoryService->page('architecture', ['page' => $page, 'sort' => $sort]);
            $reference = $pdo->prepare(
                'SELECT p.id FROM posts p JOIN post_category pc ON pc.post_id = p.id
                 JOIN categories c ON c.id = pc.category_id WHERE c.slug = :slug
                 ORDER BY ' . $column . ' DESC, p.id DESC LIMIT 9 OFFSET :offset'
            );
            $reference->bindValue('slug', 'architecture');
            $reference->bindValue('offset', ($page - 1) * 9, PDO::PARAM_INT);
            $reference->execute();
            $ids = array_column($model['posts'], 'id');
            expectBlog($ids === array_map('intval', array_column($reference->fetchAll(), 'id')), 'Category order differs from database');
            $evidence['category'][$sort][$page] = $ids;
        }
    }
    foreach (['0', '-1', 'abc', [], '-99999999999999999999999'] as $page) {
        expectBlog($categoryService->page('architecture', ['page' => $page])['page'] === 1, 'Invalid page normalization');
    }
    foreach (['views;DROP TABLE posts', '', [], 'unknown'] as $sort) {
        expectBlog($categoryService->page('architecture', ['sort' => $sort])['sort'] === 'date', 'Sort whitelist bypassed');
    }
    foreach ([['architecture', ['page' => '9999']], ['missing', []], ['architecture', ['page' => '999999999999999999999']]] as [$slug, $query]) {
        try {
            $categoryService->page($slug, $query);
            throw new RuntimeException('Expected category 404');
        } catch (NotFoundException) {
        }
    }
    $empty = $categoryService->page('editorial-notes', []);
    expectBlog($empty['posts'] === [] && $empty['pages'] === 1, 'Empty category first page');

    $all = selectBlog($pdo, 'SELECT id, slug, published_at FROM posts');
    $links = selectBlog($pdo, 'SELECT post_id, category_id FROM post_category');
    $map = [];
    foreach ($links as $row) {
        $map[(int) $row['post_id']][] = (int) $row['category_id'];
    }
    $isolated = selectBlog($pdo, "SELECT p.slug FROM posts p JOIN post_category pc ON pc.post_id = p.id
        JOIN categories c ON c.id = pc.category_id WHERE c.slug = 'engineering'")[0]['slug'];
    $evidence['isolated_slug'] = $isolated;
    foreach (['sustainable-systems', $isolated] as $slug) {
        $current = $posts->findBySlug($slug);
        $ranked = array_values(array_filter($all, static fn (array $p): bool => (int) $p['id'] !== $current->id));
        usort($ranked, static function (array $a, array $b) use ($map, $current): int {
            $aShared = count(array_intersect($map[(int) $a['id']] ?? [], $map[$current->id]));
            $bShared = count(array_intersect($map[(int) $b['id']] ?? [], $map[$current->id]));

            return $bShared <=> $aShared ?: strcmp($b['published_at'], $a['published_at']) ?: (int) $b['id'] <=> (int) $a['id'];
        });
        $log->queries = [];
        $related = $posts->related($current->id);
        expectBlog(count($log->queries) === 1, 'Related uses multiple queries');
        $ids = array_map(static fn ($p): int => $p->id, $related);
        expectBlog($ids === array_map('intval', array_column(array_slice($ranked, 0, 3), 'id')), 'Related ranking or fallback differs');
        $relatedQuery = $log->queries[0];
        $evidence['related'][$slug] = $ids;
    }
    $log->queries = [];
    $posts->inCategory($home['categories'][0]['id'], 'views', 9, 0);
    $categoryQuery = $log->queries[0];
    $evidence['explain'] = [
        'home' => explainBlog($pdo, $homeQueries[1]),
        'categories_for_posts' => explainBlog($pdo, $homeQueries[2]),
        'category_views' => explainBlog($pdo, $categoryQuery),
        'related' => explainBlog($pdo, $relatedQuery),
    ];
    $log->queries = [];
    $posts->inCategory($home['categories'][0]['id'], 'date', 9, 0);
    $evidence['explain']['category_date'] = explainBlog($pdo, $log->queries[0]);
    if (in_array('--exercise-large', $argv, true)) {
        expectBlog(getenv('APP_ENV') === 'development', 'Large probe requires development');
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare("INSERT INTO posts (slug, title, description, content, image, views, published_at)
                VALUES (:slug, :title, 'Temporary', '<p>Temporary</p>', '', :views, :published)");
            $link = $pdo->prepare('INSERT INTO post_category (post_id, category_id) VALUES (:post_id, :category_id)');
            $categoryIds = array_column($home['categories'], 'id');
            for ($i = 0; $i < 10000; $i++) {
                $insert->execute(['slug' => 'explain-probe-' . $i, 'title' => 'Temporary ' . $i,
                    'views' => $i, 'published' => '2026-01-01 12:00:00']);
                $link->execute(['post_id' => (int) $pdo->lastInsertId(), 'category_id' => $categoryIds[$i % count($categoryIds)]]);
            }
            foreach (['date', 'views'] as $sort) {
                $log->queries = [];
                $posts->inCategory($categoryIds[0], $sort, 9, 0);
                $plan = explainBlog($pdo, $log->queries[0]);
                $evidence['large_explain'][$sort] = $plan;
                expectBlog(!in_array('ALL', array_column($plan['plan'], 'type'), true), 'Full table scan on large category data');
            }
            $evidence['large_probe_posts'] = 10000;
        } finally {
            $pdo->rollBack();
        }
    }
    echo json_encode(['result' => 'OK'] + $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    file_put_contents('php://stderr', (string) $exception . PHP_EOL);
    exit(1);
}
