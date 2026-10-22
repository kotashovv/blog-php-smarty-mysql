<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Repository\SeedRepository;

$root = getenv('APP_ROOT') ?: dirname(__DIR__);
require $root . '/vendor/autoload.php';
$pdo = (new Database(new Config($root)))->connection();
$snapshot = static function () use ($pdo): string {
    $data = [];
    foreach (['categories', 'posts', 'post_category'] as $table) {
        $statement = $pdo->prepare('SELECT * FROM ' . $table . ' ORDER BY 1, 2');
        $statement->execute();
        $data[$table] = $statement->fetchAll();
    }

    return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
};
$original = getenv('APP_ENV');
$before = $snapshot();
$results = [];
try {
    foreach (['production', 'staging', null] as $environment) {
        putenv($environment === null ? 'APP_ENV' : 'APP_ENV=' . $environment);
        $process = proc_open([PHP_BINARY, $root . '/bin/seed.php', '--fresh'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot run seed guard probe');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 1 || !str_contains($stderr, '--fresh requires APP_ENV=development') || $stdout !== '') {
            throw new RuntimeException('CLI fresh guard failed');
        }
        $blocked = false;
        try {
            (new SeedRepository($pdo))->save([], [], true);
        } catch (RuntimeException $exception) {
            $blocked = str_contains($exception->getMessage(), '--fresh requires APP_ENV=development');
        }
        if (!$blocked || $snapshot() !== $before) {
            throw new RuntimeException('Repository fresh guard failed or database changed');
        }
        $results[] = ['environment' => $environment ?? 'unset', 'cli_exit' => $code, 'repository_blocked' => true, 'database_unchanged' => true];
    }
    echo json_encode(['result' => 'OK', 'sha256' => $before, 'cases' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    putenv($original === false ? 'APP_ENV' : 'APP_ENV=' . $original);
}
