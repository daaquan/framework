<?php

declare(strict_types=1);

namespace Phare\Routing;

class MiddlewareApplicator
{
    /**
     * Apply middleware list in order with optional lifecycle callbacks.
     *
     * @param array<int, string> $middlewares
     * @param callable(string): void $apply
     * @param null|callable(string): void $onStart
     * @param null|callable(string): void $onEnd
     */
    public function apply(
        array $middlewares,
        callable $apply,
        ?callable $onStart = null,
        ?callable $onEnd = null
    ): void {
        foreach ($middlewares as $middleware) {
            if ($onStart !== null) {
                $onStart($middleware);
            }
            $apply($middleware);
            if ($onEnd !== null) {
                $onEnd($middleware);
            }
        }
    }
}
