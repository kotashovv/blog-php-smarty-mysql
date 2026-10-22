<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;
use App\Core\View;
use App\Service\HomeService;

final class HomeController
{
    public function __construct(private readonly View $view, private readonly HomeService $content)
    {
    }

    public function index(): Response
    {
        return $this->view->render('home.tpl', $this->content->page());
    }
}
