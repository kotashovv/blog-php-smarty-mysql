<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Post;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;

final class HomeService
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly PostRepository $posts,
        private readonly PostPresenter $presenter = new PostPresenter(),
        private readonly PagePresenter $pages = new PagePresenter()
    ) {
    }

    public function page(): array
    {
        $categories = $this->categories->withPosts();
        $latest = $this->posts->latestByCategory();
        $unique = [];
        foreach ($latest as $posts) {
            foreach ($posts as $post) {
                $unique[$post->id] = $post;
            }
        }
        $postCategories = $this->categories->forPosts(array_keys($unique));
        $sections = [];
        foreach ($categories as $category) {
            $sections[] = PagePresenter::category($category) + [
                'posts' => $this->presenter->collection($latest[$category->id] ?? [], $postCategories),
            ];
        }
        $newest = array_values($unique);
        usort(
            $newest,
            static fn (Post $a, Post $b): int => strcmp($b->publishedAt, $a->publishedAt) ?: $b->id <=> $a->id
        );
        $hero = $newest === [] ? null : $this->presenter->present($newest[0], $postCategories[$newest[0]->id] ?? []);

        // Between the sections, never first or last; with fewer than three sections it closes the page.
        $count = count($sections);
        $newsletterAfter = $count >= 3 ? intdiv($count + 1, 2) : $count;

        return $this->pages->common('Статьи о цифровых системах', 'Архитектура, дизайн и инженерная практика.', $categories, '/') + [
            'hero' => $hero, 'categories' => $sections, 'newsletterAfter' => $newsletterAfter,
            'categoryChips' => array_map(PagePresenter::category(...), $categories),
        ];
    }
}
