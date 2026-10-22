<?php

declare(strict_types=1);

use App\Controller\CategoryController;
use App\Controller\ErrorController;
use App\Controller\HomeController;
use App\Controller\PostController;
use App\Controller\SubscribeController;
use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Repository\SubscriberRepository;
use App\Service\CategoryService;
use App\Service\HomeService;
use App\Service\PostService;
use App\Service\ViewTracker;

$config = new Config(dirname(__DIR__));
$view = new View($config);
$database = new Database($config);
$errors = new ErrorController($view);
$router = new Router();
// Resolve the lazy database only inside a handler, so errors still render without a database.
$repositories = static fn (): array => [
    new CategoryRepository($database->connection()), new PostRepository($database->connection()),
];
$router->get('/', static function () use ($repositories, $view): Response {
    [$categories, $posts] = $repositories();

    return (new HomeController($view, new HomeService($categories, $posts)))->index();
});
$router->get('/category/{slug}', static function (array $params, array $query) use ($repositories, $view): Response {
    [$categories, $posts] = $repositories();

    return (new CategoryController($view, new CategoryService($categories, $posts)))->show($params['slug'], $query);
});
$router->get('/post/{slug}', static function (array $params, array $query, string $method) use ($repositories, $view): Response {
    [$categories, $posts] = $repositories();
    $content = new PostService($categories, $posts, new ViewTracker($posts));

    return (new PostController($view, $content))->show($params['slug'], $method);
});
$router->post('/subscribe', static function () use ($database, $view): Response {
    $pdo = $database->connection();

    return (new SubscribeController($view, new SubscriberRepository($pdo), new CategoryRepository($pdo)))->store($_POST);
});

return new Application($router, $errors);
