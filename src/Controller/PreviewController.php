<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
use App\Core\Response;
use App\Core\View;
use App\Service\DemoContent;

/** Preserves the completed layout preview while the database stages await review. */
final class PreviewController
{
    public function __construct(private readonly View $view, private readonly DemoContent $content)
    {
    }

    public function category(string $slug): Response
    {
        if ($slug !== 'architecture') {
            throw new NotFoundException();
        }

        return $this->view->render('category.tpl', $this->content->page('category'));
    }

    public function post(string $slug): Response
    {
        if ($slug !== 'sustainable-systems') {
            throw new NotFoundException();
        }

        return $this->view->render('post.tpl', $this->content->page('post'));
    }
}
