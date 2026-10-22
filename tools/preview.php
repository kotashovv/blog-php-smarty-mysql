<?php

declare(strict_types=1);

// Development preview only. Production controllers assign their own view models.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/assets/')) {
    return false;
}

$routes = ['/' => 'home', '/category' => 'category', '/post' => 'post'];
$view = $routes[$path] ?? '404';
$smarty = require dirname(__DIR__) . '/config/smarty.php';
$data = json_decode(file_get_contents(__DIR__ . '/fixtures/views.json'), true, 512, JSON_THROW_ON_ERROR);
$model = array_replace($data['common'], $data[$view]);
foreach ($model['navigation'] as &$item) {
    $item['active'] = $view === 'home' ? $item['url'] === '/' : $item['label'] === 'АРХИТЕКТУРА';
}
unset($item);
if ($view === 'category') {
    $model['sort'] = ($_GET['sort'] ?? '') === 'views' ? 'views' : 'date';
    $model['page'] = max(1, min($model['pages'], (int) ($_GET['page'] ?? 2)));
    usort($model['posts'], $model['sort'] === 'views'
        ? static fn (array $a, array $b): int => $b['views'] <=> $a['views']
        : static fn (array $a, array $b): int => strcmp($b['datetime'], $a['datetime']));
}
if ($view === '404') {
    http_response_code(404);
}
$smarty->assign($model);
$smarty->display($view . '.tpl');
