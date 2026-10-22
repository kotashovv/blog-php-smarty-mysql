<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\PostRepository;

final class ViewTracker
{
    public function __construct(private readonly PostRepository $posts)
    {
    }

    /**
     * DEMO RULE: every GET of an article page is one view, so a click or a plain refresh counts.
     * This keeps the sorting by views easy to verify by hand. A production blog would deduplicate
     * (per session / IP window) and skip bots; the atomic `views = views + 1` stays the same.
     */
    public function record(int $postId): bool
    {
        $this->posts->incrementViews($postId);

        return true;
    }
}
