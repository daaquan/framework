<?php

namespace Phare\Attributes;

/**
 * @see https://www.youtube.com/watch?v=I7WJa-he5oM
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Route
{
    public const DEFAULT_REGEX = '[\w\-]+';

    /**
     * @var array<string, string>
     */
    private array $parameters = [];

    /**
     * @param array<int, string> $methods
     * @param array<int, string> $middlewares
     */
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

    /**
     * @return array<int, string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @return array<int, string>
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Checks the presence of parameters in the path of the route
     */
    public function hasParams(): bool
    {
        return preg_match('/{([\w\-%]+)(<(.+)>)?}/', $this->pattern) === 1;
    }

    /**
     * Retrieves in key of the array, the names of the parameters as well as the regular
     * expression (if there is one) in value
     *
     * @return array<string, string>
     */
    public function fetchParams(): array
    {
        if (empty($this->parameters)) {
            preg_match_all('/{([\w\-%]+)(?:<(.+?)>)?}/', $this->getPattern(), $params);
            $this->parameters = array_combine($params[1], $params[2]) ?: [];
        }

        return $this->parameters;
    }
}
