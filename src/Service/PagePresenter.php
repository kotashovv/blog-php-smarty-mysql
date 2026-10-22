<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Category;

/** Interface copy and formatting live here, never in SQL or demo fixtures. */
final class PagePresenter
{
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
        $links = array_map(self::category(...), $categories);
        $navigation = [['label' => 'Главная', 'url' => '/', 'active' => $active === '/']];
        foreach (array_slice($links, 0, 3) as $link) {
            $navigation[] = array_replace($link, ['active' => $active === $link['url']]);
        }

        return [
            'meta' => ['title' => $title . ' / Блог', 'description' => $description],
            'site' => [
                'name' => 'БЛОГ', 'language' => 'ru', 'homeUrl' => '/',
                'copyright' => '© ' . gmdate('Y') . ' Блог',
            ],
            'ui' => [
                'skipContent' => 'К содержимому', 'mainNavigation' => 'Основная навигация',
                'footerNavigation' => 'Разделы блога', 'menu' => 'Меню /', 'categories' => 'Категории',
                'allPosts' => 'Все статьи', 'breadcrumbs' => 'Путь к странице', 'page' => 'Страница',
                'pagination' => 'Страницы категории', 'previous' => 'Назад', 'next' => 'Далее',
                'noPosts' => 'В этой категории пока нет статей.', 'sortLabel' => 'Сортировка статей',
                'relatedPosts' => 'Похожие статьи',
            ],
            'navigation' => $navigation,
            'footerLinks' => $links,
            'sortOptions' => [
                ['value' => 'date', 'label' => 'По дате'], ['value' => 'views', 'label' => 'По просмотрам'],
            ],
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
