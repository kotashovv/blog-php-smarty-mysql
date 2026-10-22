<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class Router
{
    /** @var list<array{pattern: string, handler: callable}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
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
        $this->routes[] = ['pattern' => '~^' . $pattern . '$~D', 'handler' => $handler];
    }

    public function dispatch(string $method, string $uri): Response
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path)) {
            throw new NotFoundException();
        }
        $path = rawurldecode($path);
        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }
            if ($method !== 'GET') {
                throw new MethodNotAllowedException();
            }
            $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return ($route['handler'])($parameters);
        }

        throw new NotFoundException();
    }
}
