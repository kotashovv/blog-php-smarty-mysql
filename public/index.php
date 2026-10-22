<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $exception): void {
    error_log((string) $exception);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Внутренняя ошибка сервера. Попробуйте позже.';
});

require dirname(__DIR__) . '/vendor/autoload.php';
$application = require dirname(__DIR__) . '/config/bootstrap.php';
$application->handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'])->send();
