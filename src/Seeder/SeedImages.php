<?php

declare(strict_types=1);

namespace App\Seeder;

use RuntimeException;

final class SeedImages
{
    private readonly array $colors;
    private readonly string $directory;

    public function __construct(string $root)
    {
        $tokens = json_decode(file_get_contents($root . '/design/tokens.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->colors = $tokens['colors'];
        foreach ([$this->colors['ink'], ...array_values($this->colors['tones'])] as $color) {
            if (preg_match('/^#[0-9a-f]{6}$/iD', $color) !== 1) {
                throw new RuntimeException('Invalid color in design/tokens.json.');
            }
        }
        $this->directory = $root . '/public/uploads/seed';
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Cannot create the seed image directory.');
        }
    }

    public function generate(int $number, string $title): string
    {
        $tones = $this->colors['tones'];
        $tone = [$tones['lime'], $tones['blue'], $tones['lavender'], $tones['yellow']][($number - 1) % 4];
        $ink = $this->colors['ink'];
        $white = $tones['white'];
        $offset = ($number % 5) * 32;
        $title = htmlspecialchars($title, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800" role="img">
          <title>{$title}</title>
          <desc>Геометрическая редакционная иллюстрация в пастельной палитре Bento Editorial.</desc>
          <path fill="{$tone}" d="M32 0H1200V768L1168 800H0V32Z"/>
          <path fill="{$ink}" d="M160 240H1040V640H160Z"/>
          <path fill="{$white}" d="M192 272H1008V304H192ZM192 576H1008V608H192Z"/>
          <g transform="translate({$offset} 0)" fill="{$tone}">
            <path d="M224 336H352V544H224ZM384 336H512V544H384ZM544 336H672V544H544Z"/>
          </g>
          <path fill="{$ink}" d="M64 96H544V128H64ZM64 144H352V160H64ZM1008 80H1136V208H1104V112H1008Z"/>
          <path fill="{$white}" d="M736 368H976V400H736ZM736 432H976V464H736Z"/>
        </svg>
        SVG;
        $filename = sprintf('post-%04d.svg', $number);
        $path = $this->directory . '/' . $filename;
        if (!is_file($path) || file_get_contents($path) !== $svg) {
            if (file_put_contents($path, $svg, LOCK_EX) === false) {
                throw new RuntimeException('Cannot write seed image: ' . $filename);
            }
        }

        return '/uploads/seed/' . $filename;
    }

    /** Delete only obsolete files in this generator's fixed namespace, after a successful seed. */
    public function removeObsolete(int $count): void
    {
        foreach (glob($this->directory . '/post-*.svg') ?: [] as $path) {
            if (preg_match('/^post-([0-9]+)\.svg$/D', basename($path), $match) === 1 && (int) $match[1] > $count) {
                if (!unlink($path)) {
                    throw new RuntimeException('Cannot remove obsolete seed image.');
                }
            }
        }
    }
}
