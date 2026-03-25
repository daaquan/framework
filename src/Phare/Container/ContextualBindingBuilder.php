<?php

declare(strict_types=1);

namespace Phare\Container;

class ContextualBindingBuilder
{
    public function __construct(
        private Container $container,
        private array $concretes
    ) {
    }

    public function needs(string $abstract): ContextualBindingNeedsBuilder
    {
        return new ContextualBindingNeedsBuilder($this->container, $this->concretes, $abstract);
    }
}
