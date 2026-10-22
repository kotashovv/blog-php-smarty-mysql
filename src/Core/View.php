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

        return $smarty;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data, int $status = 200): Response
    {
        $this->smarty->clearAllAssign();
        $this->smarty->assign($data);
        $this->smarty->assign('tones', ['lime', 'blue', 'lavender', 'yellow']);

        return new Response($this->smarty->fetch($template), $status);
    }
}
