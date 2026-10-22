<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;
use App\Core\View;
use App\Service\DemoContent;

final class HomeController
{
    public function __construct(private readonly View $view, private readonly DemoContent $content)
    {
    }

    public function index(): Response
    {
        return $this->view->render('home.tpl', $this->content->page('home'));
    }
}
