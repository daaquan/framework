<?php

namespace Phare\View;

use Closure;

abstract class ViewComposer
{
    /**
     * Bind data to the view.
     */
    abstract public function compose(View $view);

    /**
     * Get the data array for the composer.
     */
    protected function data(): array
    {
        return [];
    }

    /**
     * Share data with the view.
     */
    protected function share(View $view, array $data): void
    {
        foreach ($data as $key => $value) {
            $view->with($key, $value);
        }
    }
}