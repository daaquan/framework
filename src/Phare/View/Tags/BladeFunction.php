<?php

namespace Phare\View\Tags;

trait BladeFunction
{
    /** @noinspection PhpUnused */
    protected function compileLang($expression)
    {
        return "<?= __$expression ?>";
    }

    /** @noinspection PhpUnused */
    protected function compileConfig($expression)
    {
        return "<?= config$expression ?>";
    }
}
