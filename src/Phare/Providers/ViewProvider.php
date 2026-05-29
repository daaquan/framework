<?php

declare(strict_types=1);

namespace Phare\Providers;

use Phalcon\Mvc\View;
use Phare\Support\ServiceProvider;

class ViewProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('view', View::class);
    }
}
