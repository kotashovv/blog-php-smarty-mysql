<?php

declare(strict_types=1);

namespace App\Seeder;

use RuntimeException;

/** Committed WebP assets are reused; seeding never regenerates or downloads images. */
final class SeedImages
{
    public const FILES = [
        '01-concrete-light.webp',
        '02-modular-pavilion.webp',
        '03-paper-grid.webp',
        '04-data-vault.webp',
        '05-connected-forms.webp',
        '06-reading-room.webp',
        '07-optical-layers.webp',
        '08-workshop-precision.webp',
        '09-garden-system.webp',
        '10-mechanical-balance.webp',
    ];

    public function __construct(string $root)
    {
        foreach ($this->urls() as $url) {
            if (!is_file($root . '/public' . $url)) {
                throw new RuntimeException('Missing editorial image: ' . $url);
            }
        }
    }

    public function forPost(int $number): string
    {
        return $this->urls()[($number - 1) % count(self::FILES)];
    }

    /** @return list<string> */
    public function urls(): array
    {
        return array_map(static fn (string $file): string => '/assets/images/editorial/' . $file, self::FILES);
    }
}
