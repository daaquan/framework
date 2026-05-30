<?php

namespace Phare\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
readonly class RouteAttribute
{
    /**
     * @param array<int, string> $middlewares
     */
    public function __construct(private array $middlewares = []) {}

    /**
     * @return array<int, string>
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * @return array{middleware?: array<int, string>}
     */
    public function getParameters(): array
    {
        $parameters = [];

        if ($this->middlewares) {
            $parameters['middleware'] = $this->middlewares;
        }

        return $parameters;
    }
}
