<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;
use App\Core\View;
use App\Service\CategoryService;

final class CategoryController
{
    public function __construct(private readonly View $view, private readonly CategoryService $content)
    {
    }

    public function show(string $slug, array $query): Response
    {
        return $this->view->render('category.tpl', $this->content->page($slug, $query));
    }
}
