<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Repository\SeedRepository;
use App\Seeder\SeedImages;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $config = new Config(dirname(__DIR__));
    $images = new SeedImages($config->root);
    $repository = new SeedRepository((new Database($config))->connection());
    echo json_encode(['updated' => $repository->refreshImages($images->urls())], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Image refresh failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
