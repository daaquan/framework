<?php

declare(strict_types=1);

namespace Phare\Routing;

use Phalcon\Http\RequestInterface;
use Phare\Contracts\Http\Validation\Validator;

class ControllerActionParameterResolver
{
    /**
     * Resolve controller action parameters from route metadata and URL params.
     *
     * @param array<int, string|null> $paramTypes
     * @param array<string, mixed> $urlParams
     * @param callable(string): mixed $make
     * @param callable(RequestInterface): void $onRequestResolved
     * @return array<int, mixed>
     */
    public function resolve(
        array $paramTypes,
        array $urlParams,
        callable $make,
        callable $onRequestResolved
    ): array {
        $params = [];
        $routeValues = array_values($urlParams);

        // URL values fill untyped/scalar args in order; injected objects
        // (Request, validators) do not consume a URL value.
        foreach ($paramTypes as $paramType) {
            if ($paramType === null) {
                $params[] = array_shift($routeValues);

                continue;
            }

            if (in_array($paramType, ['string', 'int', 'float', 'bool'], true)) {
                $value = array_shift($routeValues);
                settype($value, $paramType);
                $params[] = $value;

                continue;
            }

            $instance = $make($paramType);

            if ($instance instanceof Validator) {
                $data = method_exists($instance, 'all') ? $instance->all() : [];
                if (!$instance->validate($data)) {
                    $messages = $instance->getMessages();
                    $message = is_array($messages) ? ($messages['message'] ?? 'Unknown validation error') : (string)$messages;

                    throw new \RuntimeException('Request validation failed. ' . $message);
                }
            }

            if ($instance instanceof RequestInterface) {
                $onRequestResolved($instance);
            }

            $params[] = $instance;
        }

        return $params;
    }
}
