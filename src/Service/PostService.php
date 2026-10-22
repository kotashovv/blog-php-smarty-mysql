<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\NotFoundException;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;

final class PostService
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly PostRepository $posts,
        private readonly ViewTracker $tracker,
        private readonly PostPresenter $presenter = new PostPresenter(),
        private readonly PagePresenter $pages = new PagePresenter()
    ) {
    }

    public function page(string $slug, string $method): array
    {
        $post = $this->posts->findBySlug($slug) ?? throw new NotFoundException();
        $added = $method === 'GET' && $this->tracker->record($post->id);
        $related = $this->posts->related($post->id);
        $ids = [$post->id, ...array_map(static fn ($item): int => $item->id, $related)];
        $categories = $this->categories->forPosts($ids);
        $postCategories = $categories[$post->id] ?? [];
        $breadcrumbs = [['label' => 'Главная', 'url' => '/']];
        if ($postCategories !== []) {
            $breadcrumbs[] = ['label' => $postCategories[0]->name, 'url' => '/category/' . $postCategories[0]->slug];
        }
        $breadcrumbs[] = ['label' => $post->title, 'url' => '/post/' . $post->slug];

        return $this->pages->common($post->title, $post->description, $this->categories->withPosts()) + [
            'post' => $this->presenter->present($post, $postCategories, (int) $added),
            'relatedPosts' => $this->presenter->collection($related, $categories),
            'breadcrumbs' => $breadcrumbs,
        ];
    }
}
