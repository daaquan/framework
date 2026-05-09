<?php

declare(strict_types=1);

namespace Phare\Routing;

class MiddlewareApplicator
{
    /**
     * Apply middleware list in order with optional lifecycle callbacks.
     *
     * @param array<int, string|callable> $middlewares
     * @param callable(string|callable): void $apply
     * @param null|callable(string|callable): void $onStart
     * @param null|callable(string|callable): void $onEnd
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
