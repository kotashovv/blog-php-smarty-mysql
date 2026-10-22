<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** One place for session settings; callers must call session_write_close() when they are done. */
final class Session
{
    public static function open(): void
    {
        if (!session_start([
            'use_strict_mode' => 1,
            'use_only_cookies' => 1,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'cookie_path' => '/',
            'cache_limiter' => '',
        ])) {
            throw new RuntimeException('Cannot start the session.');
        }
    }
}
