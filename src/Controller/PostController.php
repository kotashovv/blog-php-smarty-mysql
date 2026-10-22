<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;
use App\Core\View;
use App\Service\PostService;

final class PostController
{
    public function __construct(private readonly View $view, private readonly PostService $content)
    {
    }

    public function show(string $slug, string $method): Response
    {
        $response = $this->view->render('post.tpl', $this->content->page($slug, $method));

        return new Response($response->body, $response->status, $response->headers + ['Cache-Control' => 'private, no-store']);
    }
}
