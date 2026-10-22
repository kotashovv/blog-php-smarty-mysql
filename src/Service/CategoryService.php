<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\NotFoundException;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;

final class CategoryService
{
    private const PAGE_SIZE = 9;

    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly PostRepository $posts,
        private readonly PostPresenter $presenter = new PostPresenter(),
        private readonly PagePresenter $pages = new PagePresenter()
    ) {
    }

    public function page(string $slug, array $query): array
    {
        $category = $this->categories->findBySlug($slug) ?? throw new NotFoundException();
        $sort = is_string($query['sort'] ?? null) && in_array($query['sort'], ['date', 'views'], true)
            ? $query['sort'] : 'date';
        $dir = ($query['dir'] ?? null) === 'asc' ? 'asc' : 'desc';
        $page = max(1, is_scalar($query['page'] ?? null) ? (int) $query['page'] : 1);
        $total = $this->posts->countInCategory($category->id);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        if ($page > $pages) {
            throw new NotFoundException();
        }
        $posts = $this->posts->inCategory($category->id, $sort, $dir, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);
        $categories = $this->categories->forPosts(array_map(static fn ($post): int => $post->id, $posts));
        $url = '/category/' . $category->slug;

        return $this->pages->common($category->name, $category->description, $this->categories->withPosts(), $url) + [
            'category' => PagePresenter::category($category) + ['totalLabel' => 'Статей: ' . $total],
            'posts' => $this->presenter->collection($posts, $categories),
            'page' => $page, 'pages' => $pages, 'sort' => $sort, 'dir' => $dir, 'baseUrl' => $url,
            'sortLinks' => $this->sortLinks($url, $sort, $dir),
            'breadcrumbs' => [['label' => 'Главная', 'url' => '/'], ['label' => $category->name, 'url' => $url]],
        ];
    }

    /**
     * Clicking the active sort flips its direction; switching to another sort starts from "desc".
     *
     * @return list<array{label: string, url: string, active: bool, arrow: string, title: string}>
     */
    private function sortLinks(string $baseUrl, string $sort, string $dir): array
    {
        $options = [
            'date' => ['label' => 'По дате', 'desc' => 'сначала новые', 'asc' => 'сначала старые'],
            'views' => ['label' => 'По просмотрам', 'desc' => 'сначала популярные', 'asc' => 'сначала с меньшим числом просмотров'],
        ];
        $links = [];
        foreach ($options as $value => $option) {
            $active = $value === $sort;
            $current = $active ? $dir : 'desc';
            $next = $active ? ($dir === 'desc' ? 'asc' : 'desc') : 'desc';
            $links[] = [
                'label' => $option['label'], 'active' => $active,
                'url' => $baseUrl . '?sort=' . $value . '&dir=' . $next,
                'arrow' => $current === 'asc' ? '↑' : '↓',
                'title' => $active
                    ? $option['label'] . ': ' . $option[$dir] . '. Нажмите, чтобы изменить порядок'
                    : $option['label'] . ': ' . $option['desc'],
            ];
        }

        return $links;
    }
}
