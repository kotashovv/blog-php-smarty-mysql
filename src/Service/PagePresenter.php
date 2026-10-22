<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Category;

/** Interface copy and formatting live here, never in SQL or demo fixtures. */
final class PagePresenter
{
    private const NAV_VISIBLE = 3;

    public static function category(Category $category): array
    {
        return [
            'id' => $category->id,
            'title' => $category->name,
            'label' => $category->name,
            'description' => $category->description,
            'url' => '/category/' . $category->slug,
            'active' => false,
        ];
    }

    /** @param list<Category> $categories */
    public function common(string $title, string $description, array $categories = [], string $active = ''): array
    {
        $links = array_map(
            static fn (array $link): array => array_replace($link, ['active' => $active === $link['url']]),
            array_map(self::category(...), $categories)
        );
        // Header: "Главная" + the first three categories; the rest hide under a hover dropdown.
        $navigation = [['label' => 'Главная', 'url' => '/', 'active' => $active === '/']];
        array_push($navigation, ...array_slice($links, 0, self::NAV_VISIBLE));
        $more = array_slice($links, self::NAV_VISIBLE);

        return [
            'meta' => ['title' => $title . ' / Блог', 'description' => $description],
            'site' => [
                'name' => 'БЛОГ', 'language' => 'ru', 'homeUrl' => '/',
                'copyright' => '© ' . gmdate('Y') . ' Блог',
            ],
            'ui' => [
                'skipContent' => 'К содержимому', 'mainNavigation' => 'Основная навигация',
                'footerNavigation' => 'Разделы блога', 'menu' => 'Меню', 'openMenu' => 'Открыть меню', 'subscribe' => 'Подписаться',
                'close' => 'Закрыть', 'categories' => 'Категории',
                'allPosts' => 'Все статьи', 'breadcrumbs' => 'Путь к странице', 'page' => 'Страница',
                'pagination' => 'Страницы категории', 'previous' => 'Назад', 'next' => 'Далее',
                'noPosts' => 'В этой категории пока нет статей.', 'sortLabel' => 'Сортировка статей', 'moreLabel' => 'Остальные категории',
                'relatedPosts' => 'Похожие статьи',
            ],
            'newsletter' => [
                'action' => '/subscribe', 'label' => 'Рассылка / новые статьи', 'title' => 'Новые статьи на почту',
                'description' => 'Раз в неделю присылаем подборку свежих материалов. Без спама, отписаться можно в любой момент.',
                'emailLabel' => 'Электронная почта', 'placeholder' => 'name@example.com', 'submitLabel' => 'Подписаться',
                'consent' => 'Отправляя форму, вы соглашаетесь получать письма с новыми статьями.',
            ],
            'navigation' => $navigation,
            'navigationMore' => [
                'label' => 'Остальное', 'items' => $more,
                'active' => in_array(true, array_column($more, 'active'), true),
            ],
            'navigationAll' => [...$navigation, ...$more],
            'footerLinks' => $links,
        ];
    }

    public function error(int $status, string $title, string $description): array
    {
        return $this->common((string) $status, $description) + ['error' => [
            'systemLabel' => 'SYS.ERR // ' . $status, 'archiveLabel' => 'БЛОГ',
            'label' => 'Ошибка ' . $status, 'codeLabel' => '[ КОД / ' . $status . ' ]',
            'code' => (string) $status, 'title' => $title, 'description' => $description,
            'homeLabel' => 'На главную', 'statusLabel' => 'Запрос не выполнен',
            'navigationLabel' => 'Навигация', 'categoriesLabel' => 'Главная страница',
            'categories' => [['url' => '/', 'label' => 'На главную', 'index' => '01']],
            'footerLabel' => 'БЛОГ', 'footerCode' => (string) $status,
        ]];
    }
}
