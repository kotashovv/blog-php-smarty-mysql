<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;

$root = getenv('APP_ROOT') ?: dirname(__DIR__);
require $root . '/vendor/autoload.php';

$database = new Database(new Config($root));
$pdo = $database->connection();
if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
    throw new RuntimeException('PDO error mode is unsafe');
}
if ((bool) $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) !== false) {
    throw new RuntimeException('PDO emulates prepared statements');
}
if ($pdo !== $database->connection()) {
    throw new RuntimeException('Connection is not reused');
}
$statement = $pdo->prepare('SELECT :value AS value, @@character_set_connection AS charset');
$statement->execute(['value' => "quote' and Unicode: Привет"]);
$row = $statement->fetch();
if ($row['value'] !== "quote' and Unicode: Привет" || $row['charset'] !== 'utf8mb4') {
    throw new RuntimeException('Unicode or parameter binding failed');
}
echo "PDO: connected, native prepares, exception mode, utf8mb4, bound Unicode value, reused connection OK\n";
