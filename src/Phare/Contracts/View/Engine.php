<?php

namespace Phare\Contracts\View;

interface Engine
{
    /**
     * Render a template to a string.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string;
}
