<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use RuntimeException;

/** Temporary stage-1 data adapter. Repositories replace it in stages 3–5. */
final class DemoContent
{
    /** @var array<string, mixed> */
    private readonly array $data;

    public function __construct(Config $config)
    {
        $json = file_get_contents($config->root . '/tools/fixtures/views.json');
        if ($json === false) {
            throw new RuntimeException('Demo data is unavailable.');
        }
        $this->data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    public function page(string $name): array
    {
        $model = array_replace($this->data['common'], $this->data[$name]);
        foreach ($model['navigation'] as &$item) {
            $item['active'] = $name === 'home' ? $item['url'] === '/' : $item['label'] === 'АРХИТЕКТУРА';
        }
        unset($item);

        return $this->updateLinks($model);
    }

    /** @param array<mixed> $data @return array<mixed> */
    private function updateLinks(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->updateLinks($value);
            } elseif (is_string($value)) {
                $data[$key] = match ($value) {
                    '/category' => '/category/architecture',
                    '/post' => '/post/sustainable-systems',
                    default => $value,
                };
            }
        }

        return $data;
    }
}
