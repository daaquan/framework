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
        // BladeOne resolves nested views from dot notation (it splits on "."
        // and appends the file extension). A slash-separated name is treated as
        // a literal path with no extension, so normalize back to dots here.
        return $this->blade->run(str_replace('/', '.', $view), $data);
    }
}
