<?php

declare(strict_types=1);

use App\Controller\ErrorController;
use App\Core\Application;
use App\Core\Config;
use App\Core\MethodNotAllowedException;
use App\Core\NotFoundException;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Service\DemoContent;

require dirname(__DIR__) . '/vendor/autoload.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$router = new Router();
$router->get('/post/{slug}', static fn (array $params): Response => new Response($params['slug']));
check($router->dispatch('GET', '/post/hello-world?sort=views')->body === 'hello-world', 'Path parameters');
check($router->dispatch('HEAD', '/post/hello-world')->body === 'hello-world', 'HEAD uses the GET handler');
foreach (['/unknown', '/post/a/b', '/post/%27%20OR%20%271%27=%271', '/post/%2Fetc', '/post/UPPER'] as $uri) {
    try {
        $router->dispatch('GET', $uri);
        throw new RuntimeException('Expected a 404 for ' . $uri);
    } catch (NotFoundException) {
    }
}
try {
    $router->dispatch('POST', '/post/hello-world');
    throw new RuntimeException('POST unexpectedly accepted');
} catch (MethodNotAllowedException) {
}
echo "Router: GET/HEAD, slug parameters, query strings, invalid paths, method rejection OK\n";

$config = new Config(dirname(__DIR__));
$view = new View($config);
$content = new DemoContent($config);
$errors = new ErrorController($view, $content);
$router->get('/failure', static function (): never {
    throw new RuntimeException('PRIVATE-DETAIL-MUST-NOT-LEAK');
});
$application = new Application($router, $errors);
$logPath = $config->root . '/var/core-test-errors.log';
$originalLog = ini_get('error_log');
ini_set('error_log', $logPath);
try {
    $response = $application->handle('GET', '/failure');
} finally {
    ini_set('error_log', $originalLog);
}
check($response->status === 500, 'Server error status');
check(str_contains($response->body, 'Внутренняя ошибка сервера'), 'Server error template');
check(!str_contains($response->body, 'PRIVATE-DETAIL'), 'Exception details leaked');
check(str_contains(file_get_contents($logPath), 'PRIVATE-DETAIL'), 'Exception was not logged');
check($application->handle('GET', '/missing')->status === 404, '404 error controller');
check($application->handle('POST', '/post/hello-world')->headers['Allow'] === 'GET, HEAD', 'Allow header');
$get = $application->handle('GET', '/post/hello-world');
$head = $application->handle('HEAD', '/post/hello-world');
check($head->status === $get->status && $head->headers === $get->headers && $head->body === '', 'HEAD response');
$missingHead = $application->handle('HEAD', '/missing');
check($missingHead->status === 404 && $missingHead->body === '', 'HEAD 404 has no body');
echo "Application: 404/405/500 templates, private error details logged only OK\n";

$smarty = View::createSmarty($config->root);
check($smarty->escape_html === true, 'Global escaping is disabled');
check(str_starts_with(realpath($smarty->getCompileDir()), realpath($config->root . '/var')), 'Compile path');
echo "Smarty: automatic escaping, compile/cache directories outside public OK\n";
$first = $content->page('home');
$first['meta']['title'] = 'FIRST-RENDER-ONLY';
check(str_contains($view->render('home.tpl', $first)->body, 'FIRST-RENDER-ONLY'), 'First page model');
$second = $view->render('404.tpl', $content->page('404'));
check(!str_contains($second->body, 'FIRST-RENDER-ONLY'), 'Page data leaked between renders');
check(str_contains($second->body, 'error-page__link--lime'), 'Global tones were lost');
echo "View: isolated page models and shared tones across repeated renders OK\n";

$originalPort = getenv('STAGE1_TEST_PORT');
putenv('STAGE1_TEST_PORT=invalid');
try {
    $config->port('STAGE1_TEST_PORT', 3306);
    throw new LogicException('Invalid environment port accepted');
} catch (RuntimeException $exception) {
    check(str_contains($exception->getMessage(), 'Invalid port'), 'Unexpected config failure');
} finally {
    putenv($originalPort === false ? 'STAGE1_TEST_PORT' : 'STAGE1_TEST_PORT=' . $originalPort);
}
echo "Config: rejects invalid ports without exposing secret values OK\n";
