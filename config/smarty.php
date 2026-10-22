<?php

declare(strict_types=1);

use Smarty\Smarty;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$compileDir = $root . '/var/smarty/compile';
if (!is_dir($compileDir) && !mkdir($compileDir, 0775, true) && !is_dir($compileDir)) {
    throw new RuntimeException('Cannot create Smarty compile directory.');
}

$smarty = new Smarty();
$smarty->setTemplateDir($root . '/templates');
$smarty->setCompileDir($compileDir);
$smarty->setEscapeHtml(true);
$smarty->assign('tones', ['lime', 'blue', 'lavender', 'yellow']);

return $smarty;
