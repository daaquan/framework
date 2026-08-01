<?php

namespace Phare\Auth\Sanctum;

use Phare\Support\ServiceProvider;

class SanctumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('sanctum', function () {
            return new Sanctum();
        });
    }

    public function boot(): void
    {
        $this->registerGuard();
    }

    protected function registerGuard(): void
    {
        $auth = $this->app->make('auth');

        if (method_exists($auth, 'extend')) {
            $auth->extend('sanctum', function ($app, $name, array $config) {
                return new SanctumGuard(
                    $app->make('request'),
                    $app->make('auth.user_provider')->createUserProvider($config['provider'] ?? null)
                );
            });
        }
    }
}
