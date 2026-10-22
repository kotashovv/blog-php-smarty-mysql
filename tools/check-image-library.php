<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Repository\SeedRepository;
use App\Seeder\BlogSeeder;
use App\Seeder\SeedImages;
use App\Seeder\SeedOptions;

$root = getenv('APP_ROOT') ?: dirname(__DIR__);
require $root . '/vendor/autoload.php';
$pdo = (new Database(new Config($root)))->connection();
$snapshot = static function (bool $includeImages = true) use ($pdo): string {
    $data = [];
    foreach (['posts', 'categories', 'post_category'] as $table) {
        $statement = $pdo->prepare('SELECT * FROM ' . $table . ' ORDER BY 1, 2');
        $statement->execute();
        $rows = $statement->fetchAll();
        if ($table === 'posts' && !$includeImages) {
            foreach ($rows as &$row) {
                unset($row['image']);
            }
            unset($row);
        }
        $data[$table] = $rows;
    }

    return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
};
$refresh = static function () use ($root): int {
    $process = proc_open([PHP_BINARY, $root . '/bin/refresh-images.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start image refresh');
    }
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException($error);
    }

    return json_decode($output, true, 512, JSON_THROW_ON_ERROR)['updated'];
};

$before = $snapshot(false);
$updated = $refresh();
if ($snapshot(false) !== $before) {
    throw new RuntimeException('Image refresh changed content, views or relations');
}
$after = $snapshot();
if ($refresh() !== 0 || $snapshot() !== $after) {
    throw new RuntimeException('Image refresh is not idempotent');
}
$images = new SeedImages($root);
for ($number = 1; $number <= 40; $number++) {
    if ($images->forPost($number) !== $images->forPost($number + 10)) {
        throw new RuntimeException('Image selection is not cyclic');
    }
}
$statement = $pdo->prepare('SELECT image, COUNT(*) AS posts FROM posts GROUP BY image ORDER BY image');
$statement->execute();
$usage = $statement->fetchAll();
if (count($usage) !== 10 || array_diff(array_column($usage, 'image'), $images->urls()) !== []) {
    throw new RuntimeException('Articles do not reuse the full WebP library');
}
foreach ($images->urls() as $url) {
    $info = getimagesize($root . '/public' . $url);
    if ($info === false || $info['mime'] !== 'image/webp' || $info[0] !== 1200 || $info[1] !== 800) {
        throw new RuntimeException('Invalid WebP image: ' . $url);
    }
}
(new BlogSeeder(new SeedRepository($pdo), $images))->run(SeedOptions::fromArguments([]));
if ($snapshot() !== $after) {
    throw new RuntimeException('Normal seed changed existing data or image paths');
}
echo json_encode([
    'result' => 'OK', 'updated' => $updated, 'second_refresh_updated' => 0,
    'content_views_relations_preserved' => true, 'normal_seed_preserved' => true,
    'other_fields_sha256' => $before, 'usage' => $usage,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
