<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;
use App\Core\View;
use App\Service\DemoContent;

final class ErrorController
{
    public function __construct(private readonly View $view, private readonly DemoContent $content)
    {
    }

    public function notFound(): Response
    {
        return $this->view->render('404.tpl', $this->content->page('404'), 404);
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
        $data = $this->content->page('404');
        $data['meta'] = ['title' => $status . ' / ' . $title, 'description' => $description];
        $data['error'] = array_replace($data['error'], [
            'systemLabel' => 'SYS.ERR // ' . $status,
            'label' => 'Ошибка ' . $status,
            'codeLabel' => '[ КОД / ' . $status . ' ]',
            'code' => (string) $status,
            'title' => $title,
            'description' => $description,
            'statusLabel' => 'Статус: запрос не выполнен',
        ]);

        return $this->view->render('404.tpl', $data, $status);
    }
}
