<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Config
{
    public function __construct(public readonly string $root)
    {
    }

    public function string(string $name, ?string $default = null): string
    {
        $value = getenv($name);
        if ($value === false) {
            $value = $default;
        }
        if ($value === null || $value === '') {
            throw new RuntimeException('Missing environment variable: ' . $name);
        }

        return $value;
    }

    public function port(string $name, int $default): int
    {
        $value = filter_var($this->string($name, (string) $default), FILTER_VALIDATE_INT);
        if ($value === false || $value < 1 || $value > 65535) {
            throw new RuntimeException('Invalid port environment variable: ' . $name);
        }

        return $value;
    }
}
