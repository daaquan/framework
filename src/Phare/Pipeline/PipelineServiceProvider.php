<?php

namespace Phare\Pipeline;

use Phare\Support\ServiceProvider;

class PipelineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Pipeline::class, function () {
            return new Pipeline($this->app);
        });
    }
}
