<?php

namespace Phare\Auth\Middleware;

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Foundation\Application;
use Phare\Contracts\Http\MiddlewareContract;
use Phare\Foundation\Http\Concerns\BeforeMiddleware;
use Phare\Foundation\Http\ResponseStatusCode;
use Phare\Foundation\Micro;
use Phare\Support\Facades\Auth;

/**
 * Role-based authorization middleware.
 * Register as 'role.admin', 'role.staff', etc. in routeMiddleware,
 * or as a class with roles passed via constructor.
 */
class EnsureRole extends MiddlewareContract implements BeforeMiddleware
{
    private array $roles;

    public function __construct(private Application $app, string ...$roles)
    {
        $this->roles = $roles;
    }

    public function handle(RequestInterface $request, ResponseInterface $response)
    {
        $user = Auth::user();

        if (!$user) {
            $this->app->stop();
            $response->redirect(route('login'));

            return false;
        }

        if (!empty($this->roles) && !in_array($user->role, $this->roles, true)) {
            $this->app->stop();
            $response->setStatusCode(ResponseStatusCode::FORBIDDEN->value, 'Forbidden');

            if ($this->app instanceof Micro) {
                $response->setJsonContent(['message' => 'Forbidden'])->send();
            } else {
                $response->redirect('/');
            }

            return false;
        }

        return true;
    }
}
