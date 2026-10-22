<?php

declare(strict_types=1);

// CLI-only probe: execute a temporary file outside public through the real FPM worker.
// No debug HTTP route or permanent PHP file is added to the running application.
$root = getenv('APP_ROOT') ?: dirname(__DIR__);
$marker = 'STAGE1_REVIEW_LOG_' . bin2hex(random_bytes(8));
$probe = $root . '/var/fpm-review-' . bin2hex(random_bytes(8)) . '.php';
$socket = null;
$exitCode = 0;

function fpmRecord(int $type, string $content): string
{
    return pack('CCnnCC', 1, $type, 1, strlen($content), 0, 0) . $content;
}

function fpmLength(int $length): string
{
    return $length < 128 ? chr($length) : pack('N', $length | 0x80000000);
}

function fpmRead($socket, int $length): string
{
    $result = '';
    while (strlen($result) < $length) {
        $part = fread($socket, $length - strlen($result));
        if ($part === false || $part === '') {
            throw new RuntimeException('Incomplete or timed-out FastCGI response.');
        }
        $result .= $part;
    }

    return $result;
}

try {
    $code = <<<'PHP'
    <?php
    declare(strict_types=1);
    require dirname(__DIR__) . '/vendor/autoload.php';
    $config = new App\Core\Config(dirname(__DIR__));
    $view = new App\Core\View($config);
    $content = new App\Service\DemoContent($config);
    $errors = new App\Controller\ErrorController($view, $content);
    $router = new App\Core\Router();
    $router->get('/internal-log-probe', static function (): never {
        throw new RuntimeException($_SERVER['REVIEW_LOG_MARKER']);
    });
    (new App\Core\Application($router, $errors))->handle('GET', '/internal-log-probe')->send();
    PHP;
    if (file_put_contents($probe, $code, LOCK_EX) === false) {
        throw new RuntimeException('Cannot create temporary FPM probe.');
    }
    $socket = stream_socket_client('tcp://127.0.0.1:9000', $errno, $message, 10);
    if ($socket === false) {
        throw new RuntimeException('Cannot connect to the local FPM worker.');
    }
    stream_set_timeout($socket, 10);
    $parameters = [
        'GATEWAY_INTERFACE' => 'CGI/1.1',
        'SERVER_PROTOCOL' => 'HTTP/1.1',
        'SERVER_NAME' => 'localhost',
        'SERVER_PORT' => '80',
        'REMOTE_ADDR' => '127.0.0.1',
        'REQUEST_METHOD' => 'GET',
        'SCRIPT_FILENAME' => $probe,
        'SCRIPT_NAME' => '/internal-log-probe',
        'REQUEST_URI' => '/internal-log-probe',
        'QUERY_STRING' => '',
        'CONTENT_LENGTH' => '0',
        'REDIRECT_STATUS' => '200',
        'REVIEW_LOG_MARKER' => $marker,
    ];
    $encoded = '';
    foreach ($parameters as $name => $value) {
        $encoded .= fpmLength(strlen($name)) . fpmLength(strlen($value)) . $name . $value;
    }
    $request = fpmRecord(1, "\x00\x01\x00\x00\x00\x00\x00\x00")
        . fpmRecord(4, $encoded) . fpmRecord(4, '') . fpmRecord(5, '');
    while ($request !== '') {
        $written = fwrite($socket, $request);
        if ($written === false || $written === 0) {
            throw new RuntimeException('Cannot send the FastCGI probe.');
        }
        $request = substr($request, $written);
    }
    $output = '';
    do {
        $header = unpack('Cversion/Ctype/nrequest/nlength/Cpadding/Creserved', fpmRead($socket, 8));
        $content = fpmRead($socket, $header['length']);
        if ($header['padding'] > 0) {
            fpmRead($socket, $header['padding']);
        }
        if ($header['type'] === 6) {
            $output .= $content;
        }
    } while ($header['type'] !== 3);
    if (preg_match('/^Status: 500\b/m', $output) !== 1 || !str_contains($output, 'Внутренняя ошибка сервера')) {
        throw new RuntimeException('FPM did not render the expected generic 500 response.');
    }
    if (str_contains($output, $marker) || str_contains($output, 'Stack trace:')) {
        throw new RuntimeException('Exception details leaked to the response.');
    }
    echo json_encode([
        'marker' => $marker,
        'sapi' => 'fpm-fcgi',
        'response_status' => 500,
        'details_hidden' => true,
        'probe_outside_public' => true,
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    file_put_contents('php://stderr', 'FPM log probe failed: ' . $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if (is_resource($socket)) {
        fclose($socket);
    }
    if (is_file($probe)) {
        unlink($probe);
    }
}

exit($exitCode);
