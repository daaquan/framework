<?php

declare(strict_types=1);

namespace Phare\Routing;

class DispatchForwardPayloadBuilder
{
    /**
     * Build a dispatcher forward payload from route metadata and resolved params.
     *
     * @param array<string, mixed> $routeData
     * @param array<string, mixed> $urlParams
     * @param callable(array<int, string|null>, array<string, mixed>): array<int, mixed> $resolveTypedParams
     * @return array{namespace: string, controller: string, action: string, params: array<int, mixed>}
     */
    public function build(
        array $routeData,
        array $urlParams,
        callable $resolveTypedParams
    ): array {
        $paramTypes = $routeData['params'] ?? [];

        if (empty($paramTypes) && !empty($urlParams)) {
            return [
                'namespace' => $routeData['namespace'],
                'controller' => $routeData['controller'],
                'action' => $routeData['action'],
                'params' => array_values($urlParams),
            ];
        }

        return [
            'namespace' => $routeData['namespace'],
            'controller' => $routeData['controller'],
            'action' => $routeData['action'],
            'params' => $resolveTypedParams($paramTypes, $urlParams),
        ];
    }
}
