<?php

declare(strict_types=1);

use App\Controller\ErrorController;
use App\Controller\HomeController;
use App\Controller\PreviewController;
use App\Core\Application;
use App\Core\Config;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Service\DemoContent;

$config = new Config(dirname(__DIR__));
$view = new View($config);
$content = new DemoContent($config);
$errors = new ErrorController($view, $content);
$home = new HomeController($view, $content);
$preview = new PreviewController($view, $content);
$router = new Router();
$router->get('/', static fn (): Response => $home->index());
$router->get('/category/{slug}', static fn (array $params): Response => $preview->category($params['slug']));
$router->get('/post/{slug}', static fn (array $params): Response => $preview->post($params['slug']));
// Backward-compatible preview links. Remove when database-backed routes replace these adapters.
$router->get('/category', static fn (): Response => new Response('', 302, ['Location' => '/category/architecture']));
$router->get('/post', static fn (): Response => new Response('', 302, ['Location' => '/post/sustainable-systems']));

return new Application($router, $errors);
