<?php

declare(strict_types=1);

namespace App\Core;

use App\Controller\ErrorController;
use Throwable;

final class Application
{
    public function __construct(private readonly Router $router, private readonly ErrorController $errors)
    {
    }

    public function handle(string $method, string $uri): Response
    {
        try {
            $response = $this->router->dispatch($method, $uri);
        } catch (NotFoundException) {
            $response = $this->errors->notFound();
        } catch (MethodNotAllowedException $exception) {
            $response = $this->errors->methodNotAllowed($exception->allow);
        } catch (Throwable $exception) {
            error_log((string) $exception);

            $response = $this->errors->serverError();
        }

        return $method === 'HEAD' ? $response->withoutBody() : $response;
    }
}
