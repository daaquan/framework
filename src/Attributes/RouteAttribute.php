<?php

namespace Phare\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
readonly class RouteAttribute
{
    public function __construct(private array $middlewares = []) {}

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function getParameters(): array
    {
        $parameters = [];

        if ($this->middlewares) {
            $parameters['middleware'] = $this->middlewares;
        }

        return $parameters;
    }
}
