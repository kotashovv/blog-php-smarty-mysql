<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Category;
use App\Model\Post;
use DateTimeImmutable;
use DateTimeZone;

final class PostPresenter
{
    /** @param list<Category> $categories */
    public function present(Post $post, array $categories, int $addedViews = 0): array
    {
        $date = new DateTimeImmutable($post->publishedAt, new DateTimeZone('UTC'));

        return [
            'id' => $post->id,
            'url' => '/post/' . $post->slug,
            'title' => $post->title,
            'excerpt' => $post->description,
            'bodyHtml' => $post->content,
            'image' => $post->image === '' ? null : [
                'src' => $post->image, 'alt' => $post->title, 'width' => 1200, 'height' => 800,
            ],
            'datetime' => $date->format('Y-m-d\TH:i:s\Z'),
            'date' => $date->format('d.m.Y'),
            'views' => $post->views + $addedViews,
            'viewsLabel' => number_format($post->views + $addedViews, 0, ',', ' ') . ' просмотров',
            'categories' => array_map(PagePresenter::category(...), $categories),
        ];
    }

    /** @param list<Post> $posts @param array<int, list<Category>> $categories */
    public function collection(array $posts, array $categories): array
    {
        return array_map(fn (Post $post): array => $this->present($post, $categories[$post->id] ?? []), $posts);
    }
}
