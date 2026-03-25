<?php

declare(strict_types=1);

namespace Phare\Routing;

class RouteMountDispatcher
{
    /**
     * Dispatch to web/micro mount strategy callback by resolved mode.
     *
     * @param callable(): void $handleWeb
     * @param callable(): void $handleMicro
     */
    public function dispatch(string $mode, callable $handleWeb, callable $handleMicro): void
    {
        if ($mode === ApplicationModeResolver::MODE_WEB) {
            $handleWeb();

            return;
        }

        if ($mode === ApplicationModeResolver::MODE_MICRO) {
            $handleMicro();

            return;
        }

        throw new \RuntimeException(sprintf('Unsupported application mode "%s".', $mode));
    }
}
