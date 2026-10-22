<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;
use App\Core\View;
use App\Service\PagePresenter;

final class ErrorController
{
    public function __construct(private readonly View $view, private readonly PagePresenter $content = new PagePresenter())
    {
    }

    public function notFound(): Response
    {
        return $this->error(404, 'МАТЕРИАЛ НЕ СУЩЕСТВУЕТ', 'Проверьте адрес или вернитесь на главную страницу.');
    }

    public function serverError(): Response
    {
        return $this->error(500, 'Внутренняя ошибка сервера', 'Попробуйте открыть страницу позже.');
    }

    public function methodNotAllowed(): Response
    {
        $response = $this->error(405, 'Метод не поддерживается', 'Для просмотра страницы используйте GET или HEAD.');

        return new Response($response->body, 405, $response->headers + ['Allow' => 'GET, HEAD']);
    }

    private function error(int $status, string $title, string $description): Response
    {
        $data = $this->content->error($status, $title, $description);

        return $this->view->render('404.tpl', $data, $status);
    }
}
