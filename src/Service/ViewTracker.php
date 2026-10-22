<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\PostRepository;
use RuntimeException;

final class ViewTracker
{
    public function __construct(private readonly PostRepository $posts)
    {
    }

    public function record(int $postId): bool
    {
        // Hold the session lock until the increment is recorded: concurrent reloads share this lock.
        if (!session_start([
            'use_strict_mode' => 1,
            'use_only_cookies' => 1,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'cookie_path' => '/',
            'cache_limiter' => '',
        ])) {
            throw new RuntimeException('Cannot start the view session.');
        }
        try {
            if (isset($_SESSION['viewed_posts'][$postId])) {
                return false;
            }
            $this->posts->incrementViews($postId);
            $_SESSION['viewed_posts'][$postId] = true;

            return true;
        } finally {
            session_write_close();
        }
    }
}
