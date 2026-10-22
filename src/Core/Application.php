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
            return $this->router->dispatch($method, $uri);
        } catch (NotFoundException) {
            return $this->errors->notFound();
        } catch (MethodNotAllowedException) {
            return $this->errors->methodNotAllowed();
        } catch (Throwable $exception) {
            error_log((string) $exception);

            return $this->errors->serverError();
        }
    }
}
