<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Smarty\Smarty;

final class View
{
    private readonly Smarty $smarty;

    public function __construct(Config $config)
    {
        $this->smarty = self::createSmarty($config->root);
    }

    public static function createSmarty(string $root): Smarty
    {
        foreach (['templates_c', 'cache'] as $directory) {
            $path = $root . '/var/' . $directory;
            if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
                throw new RuntimeException('Cannot create Smarty runtime directory.');
            }
        }
        $smarty = new Smarty();
        $smarty->setTemplateDir($root . '/templates');
        $smarty->setCompileDir($root . '/var/templates_c');
        $smarty->setCacheDir($root . '/var/cache');
        $smarty->setEscapeHtml(true);
        $smarty->assign('tones', ['lime', 'blue', 'lavender', 'yellow']);
        // Every page carries the subscribe popup, so a GET starts a session for its CSRF token.
        $smarty->registerPlugin(
            'function',
            'csrf_token',
            // HEAD gets no token (and no session cookie): its body is discarded anyway.
            static fn (): string => ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD' ? '' : Csrf::token()
        );

        return $smarty;
    }

    /** True when the visitor clicked through from another page of this site. */
    private static function isInternalNavigation(): bool
    {
        return ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'same-origin'
            && ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '') === 'navigate';
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data, int $status = 200): Response
    {
        $page = $this->smarty->createTemplate($template);
        $page->assign($data);
        $page->assign('isNavigation', self::isInternalNavigation());

        // The entrance animation depends on how the visitor arrived, so caches must not share it.
        return new Response($page->fetch(), $status, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Vary' => 'Sec-Fetch-Site',
        ]);
    }
}
