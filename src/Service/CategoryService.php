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
        $page = max(1, is_scalar($query['page'] ?? null) ? (int) $query['page'] : 1);
        $total = $this->posts->countInCategory($category->id);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        if ($page > $pages) {
            throw new NotFoundException();
        }
        $posts = $this->posts->inCategory($category->id, $sort, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);
        $categories = $this->categories->forPosts(array_map(static fn ($post): int => $post->id, $posts));
        $url = '/category/' . $category->slug;

        return $this->pages->common($category->name, $category->description, [$category], $url) + [
            'category' => PagePresenter::category($category) + ['totalLabel' => 'Статей: ' . $total],
            'posts' => $this->presenter->collection($posts, $categories),
            'page' => $page, 'pages' => $pages, 'sort' => $sort, 'baseUrl' => $url,
            'breadcrumbs' => [['label' => 'Главная', 'url' => '/'], ['label' => $category->name, 'url' => $url]],
        ];
    }
}
