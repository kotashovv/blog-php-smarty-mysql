<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Repository\SchemaRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $config = new Config(dirname(__DIR__));
    $repository = new SchemaRepository((new Database($config))->connection());
    $count = $repository->migrate($config->root . '/database/schema.sql');
    fwrite(STDOUT, "Schema ready: {$count} repeatable CREATE TABLE statements.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
