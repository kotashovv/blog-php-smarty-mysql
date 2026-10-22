<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Repository\CategoryRepository;
use App\Repository\SubscriberRepository;
use App\Service\PagePresenter;

final class SubscribeController
{
    public function __construct(
        private readonly View $view,
        private readonly SubscriberRepository $subscribers,
        private readonly CategoryRepository $categories,
        private readonly PagePresenter $pages = new PagePresenter()
    ) {
    }

    /** @param array<mixed> $input */
    public function store(array $input): Response
    {
        if (!Csrf::isValid($input['csrf_token'] ?? null)) {
            return $this->result(403, 'Форма устарела', 'Обновите страницу и отправьте адрес ещё раз.');
        }
        $email = is_string($input['email'] ?? null) ? trim($input['email']) : '';
        if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->result(422, 'Проверьте адрес', 'Укажите адрес электронной почты в формате name@example.com.');
        }
        $this->subscribers->add($email);

        return $this->result(200, 'Вы подписаны', 'Мы будем присылать новые статьи на указанный адрес.');
    }

    private function result(int $status, string $title, string $message): Response
    {
        $data = $this->pages->common($title, $message, $this->categories->withPosts()) + [
            'subscribe' => [
                'ok' => $status === 200, 'title' => $title, 'message' => $message,
                'label' => $status === 200 ? 'Подписка / готово' : 'Подписка / ошибка',
                'backUrl' => '/', 'backLabel' => 'На главную',
            ],
        ];

        return $this->view->render('subscribe.tpl', $data, $status);
    }
}
