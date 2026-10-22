<?php

declare(strict_types=1);

use App\Core\View;

require_once dirname(__DIR__) . '/vendor/autoload.php';

return View::createSmarty(dirname(__DIR__));
