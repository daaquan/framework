<?php

namespace Phare\View\Engines;

use Phare\Contracts\View\Engine;
use Phare\View\Blade;

class BladeEngine implements Engine
{
    public function __construct(protected Blade $blade) {}

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        return $this->blade->run($view, $data);
    }
}
