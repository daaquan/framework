<?php

declare(strict_types=1);

namespace Phare\Routing;

class WebDispatchForwardRegistrar
{
    /**
     * Register web dispatch forwarding listener.
     *
     * @param array<string, mixed> $routeData
     * @param array<string, mixed> $urlParams
     * @param callable(array<string, mixed>, array<string, mixed>): array<string, mixed> $buildPayload
     */
    public function register(
        object $eventsManager,
        array $routeData,
        array $urlParams,
        callable $buildPayload
    ): void {
        $eventsManager->attach(
            'dispatch:beforeExecuteRoute',
            function ($event, $dispatcher) use ($routeData, $urlParams, $buildPayload) {
                if ($dispatcher->wasForwarded()) {
                    return;
                }

                $dispatcher->forward($buildPayload($routeData, $urlParams));
            }
        );
    }
}
