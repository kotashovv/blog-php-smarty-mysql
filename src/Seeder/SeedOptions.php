<?php

declare(strict_types=1);

namespace App\Seeder;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class SeedOptions
{
    public function __construct(
        public readonly int $categories,
        public readonly int $posts,
        public readonly bool $fresh,
        public readonly DateTimeImmutable $date
    ) {
        if ($categories < 4 || $posts < 12) {
            throw new InvalidArgumentException(
                'Use at least 4 categories and 12 posts to preserve the required edge cases.'
            );
        }
    }

    /** @param list<string> $arguments */
    public static function fromArguments(array $arguments): self
    {
        $values = ['categories' => 6, 'posts' => 40];
        $seen = [];
        $fresh = false;
        foreach ($arguments as $argument) {
            if ($argument === '--fresh' && !isset($seen['fresh'])) {
                $fresh = true;
                $seen['fresh'] = true;
                continue;
            }
            if (preg_match('/^--(categories|posts)=([1-9][0-9]*)$/D', $argument, $match) !== 1) {
                throw new InvalidArgumentException('Unknown or invalid argument: ' . $argument);
            }
            $name = $match[1];
            $number = filter_var($match[2], FILTER_VALIDATE_INT);
            if ($number === false || isset($seen[$name])) {
                throw new InvalidArgumentException('Invalid or repeated option: ' . $name);
            }
            $values[$name] = $number;
            $seen[$name] = true;
        }
        $utc = new DateTimeZone('UTC');
        $dateString = getenv('SEED_DATE');
        if ($dateString === false || $dateString === '') {
            $dateString = (new DateTimeImmutable('today', $utc))->format('Y-m-d');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateString, $utc);
        if ($date === false || $date->format('Y-m-d') !== $dateString) {
            throw new InvalidArgumentException('SEED_DATE must be a valid YYYY-MM-DD date.');
        }

        return new self($values['categories'], $values['posts'], $fresh, $date);
    }
}
