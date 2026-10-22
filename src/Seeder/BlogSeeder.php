<?php

declare(strict_types=1);

namespace App\Seeder;

use App\Repository\SeedRepository;

final class BlogSeeder
{
    public function __construct(
        private readonly SeedRepository $repository,
        private readonly SeedImages $images,
        private readonly SeedContent $content = new SeedContent()
    ) {
    }

    /** @return array<string, int> */
    public function run(SeedOptions $options): array
    {
        $categories = $this->content->categories($options);
        $posts = [];
        for ($number = 1; $number <= $options->posts; $number++) {
            $post = $this->content->post($number, $options, $categories);
            $post['image'] = $this->images->forPost($number);
            $posts[] = $post;
        }
        $counts = $this->repository->save($categories, $posts, $options->fresh);

        return $counts;
    }
}
