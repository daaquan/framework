<?php

namespace Phare\Attributes;

/**
 * @see https://www.youtube.com/watch?v=I7WJa-he5oM
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Route
{
    public const DEFAULT_REGEX = '[\w\-]+';

    private array $parameters = [];

    public function __construct(
        private readonly string $pattern = '',
        private readonly array $methods = ['GET'],
        private readonly array $middlewares = [],
        private string $name = ''
    ) {
        if (empty($this->name)) {
            $this->name = 'generated-' . str_random(10);
        }
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Checks the presence of parameters in the path of the route
     */
    public function hasParams(): bool
    {
        return preg_match('/{([\w\-%]+)(<(.+)>)?}/', $this->pattern);
    }

    /**
     * Retrieves in key of the array, the names of the parameters as well as the regular
     * expression (if there is one) in value
     */
    public function fetchParams(): array
    {
        if (empty($this->parameters)) {
            preg_match_all('/{([\w\-%]+)(?:<(.+?)>)?}/', $this->getPattern(), $params);
            $this->parameters = array_combine($params[1], $params[2]);
        }

        return $this->parameters;
    }
}
