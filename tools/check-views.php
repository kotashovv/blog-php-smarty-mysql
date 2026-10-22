<?php

declare(strict_types=1);

// Templates start a CSRF session; buffer output so headers are not "sent" by earlier echo lines.
ob_start();

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$smarty = require dirname(__DIR__) . '/config/smarty.php';
$pages = new App\Service\PagePresenter();
$category = new App\Model\Category(1, 'test', 'Тестовый раздел', 'Описание раздела');
$post = new App\Model\Post(1, 'test', 'Заголовок', 'Описание', '<p>Текст</p>', '/uploads/test.svg', 12, '2026-10-21 12:00:00');
$presented = (new App\Service\PostPresenter())->present($post, [$category]);
$common = $pages->common('Тест', 'Описание', [$category]);
$data = [
    'common' => $common,
    'home' => ['hero' => $presented, 'categories' => [App\Service\PagePresenter::category($category) + ['posts' => [$presented]]], 'categoryChips' => []],
    'category' => ['category' => App\Service\PagePresenter::category($category) + ['totalLabel' => '40 статей'], 'posts' => [$presented], 'page' => 2, 'pages' => 5, 'sort' => 'date', 'dir' => 'desc', 'sortLinks' => [['label' => 'По дате', 'url' => '/category/test?sort=date&dir=asc', 'active' => true, 'arrow' => '↓', 'title' => 'По дате'], ['label' => 'По просмотрам', 'url' => '/category/test?sort=views&dir=desc', 'active' => false, 'arrow' => '↓', 'title' => 'По просмотрам']], 'baseUrl' => '/category/test', 'breadcrumbs' => []],
    'post' => ['post' => $presented, 'relatedPosts' => [$presented], 'breadcrumbs' => []],
    '404' => $pages->error(404, 'Не найдено', 'Страница отсутствует'),
];
$payload = '<script>alert("escaped")</script>';
foreach (['home', 'category', 'post', '404'] as $view) {
    $smarty->clearAllAssign();
    $smarty->assign('tones', ['lime', 'blue', 'lavender', 'yellow']);
    $model = array_replace($data['common'], $data[$view]);
    $model['meta']['title'] = $payload;
    $model['meta']['description'] = '" onload="alert(1)';
    if ($view === 'post') {
        $model['post']['bodyHtml'] = '<h2>Trusted editorial HTML</h2><p>Body content.</p>';
    }
    $smarty->assign($model);
    $html = $smarty->fetch($view . '.tpl');
    if (str_contains($html, $payload) || !str_contains($html, '&lt;script&gt;')) {
        throw new RuntimeException($view . ': automatic HTML escaping failed');
    }
    if ($view === 'post' && !str_contains($html, '<h2>Trusted editorial HTML</h2>')) {
        throw new RuntimeException('Article HTML was escaped');
    }
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    if ($xpath->query('//script | //style | //*[@style]')->length !== 0) {
        throw new RuntimeException($view . ': inline styles or scripts');
    }
    foreach ($xpath->query('//@*') as $attribute) {
        if (str_starts_with(strtolower($attribute->nodeName), 'on')) {
            throw new RuntimeException($view . ': event attribute escaped incorrectly');
        }
    }
    if ($xpath->query('//h1')->length !== 1 || $xpath->query('//main')->length !== 1) {
        throw new RuntimeException($view . ': invalid page landmarks');
    }
    foreach ($xpath->query('//img') as $img) {
        if (!$img->hasAttribute('alt')) {
            throw new RuntimeException($view . ': missing image alt');
        }
    }
    echo $view . ": render, escaping and semantics OK\n";
}

$smarty->assign(array_replace($data['common'], $data['category'], ['page' => 3, 'sort' => 'views', 'dir' => 'asc']));
$html = $smarty->fetch('category.tpl');
if (!str_contains($html, '?page=4&amp;sort=views&amp;dir=asc') || !str_contains($html, '?page=2&amp;sort=views&amp;dir=asc')) {
    throw new RuntimeException('Pagination does not retain sorting and direction');
}
echo "Pagination retains sorting and direction OK\n";
