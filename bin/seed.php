<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Repository\SeedRepository;
use App\Seeder\BlogSeeder;
use App\Seeder\SeedImages;
use App\Seeder\SeedOptions;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (array_slice($argv, 1) === ['--help']) {
    fwrite(STDOUT, "Usage: php bin/seed.php [--fresh] [--categories=6] [--posts=40]\n");
    fwrite(STDOUT, "Minimum: 4 categories, 12 posts. Optional SEED_DATE=YYYY-MM-DD (default: today UTC).\n");
    fwrite(STDOUT, "--fresh deletes all blog rows and recreates them; normal runs preserve existing rows.\n");
    exit(0);
}

try {
    $options = SeedOptions::fromArguments(array_slice($argv, 1));
    $config = new Config(dirname(__DIR__));
    $seeder = new BlogSeeder(
        new SeedRepository((new Database($config))->connection()),
        new SeedImages($config->root)
    );
    $counts = $seeder->run($options);
    fwrite(STDOUT, json_encode([
        'mode' => $options->fresh ? 'fresh' : 'preserve',
        'seed_date' => $options->date->format('Y-m-d'),
        'counts' => $counts,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Seed failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
