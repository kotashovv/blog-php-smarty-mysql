<?php

declare(strict_types=1);

namespace App\Core;

/** Session-bound token for state-changing POST forms. */
final class Csrf
{
    public static function token(): string
    {
        Session::open();
        try {
            if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
            }

            return $_SESSION['csrf'];
        } finally {
            session_write_close();
        }
    }

    public static function isValid(mixed $submitted): bool
    {
        Session::open();
        try {
            $expected = $_SESSION['csrf'] ?? null;
        } finally {
            session_write_close();
        }

        return is_string($expected) && is_string($submitted) && hash_equals($expected, $submitted);
    }
}
