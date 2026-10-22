<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class Router
{
    private const ALLOW = ['GET' => 'GET, HEAD', 'POST' => 'POST'];

    /** @var list<array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $quoted = preg_quote($path, '~');
        $pattern = preg_replace(
            '/\\\\\{([a-zA-Z][a-zA-Z0-9_]*)\\\\\}/',
            '(?P<$1>[a-z0-9-]+)',
            $quoted
        );
        if ($pattern === null || !str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Invalid route pattern.');
        }
        $this->routes[] = ['method' => $method, 'pattern' => '~^' . $pattern . '$~D', 'handler' => $handler];
    }

    public function dispatch(string $method, string $uri): Response
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path)) {
            throw new NotFoundException();
        }
        $path = rawurldecode($path);
        $lookup = $method === 'HEAD' ? 'GET' : $method;
        $allowed = [];
        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }
            if ($route['method'] !== $lookup) {
                $allowed[] = self::ALLOW[$route['method']];
                continue;
            }
            $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            $query = [];
            parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

            return ($route['handler'])($parameters, $query, $method);
        }
        if ($allowed !== []) {
            throw new MethodNotAllowedException(implode(', ', array_unique($allowed)));
        }

        throw new NotFoundException();
    }
}
