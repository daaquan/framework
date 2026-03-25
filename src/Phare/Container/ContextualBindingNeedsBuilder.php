<?php

declare(strict_types=1);

namespace Phare\Container;

class ContextualBindingNeedsBuilder
{
    public function __construct(
        private Container $container,
        private array $concretes,
        private string $abstract
    ) {
    }

    public function give($implementation): void
    {
        foreach ($this->concretes as $concrete) {
            $this->container->addContextualBinding($concrete, $this->abstract, $implementation);
        }
    }

    public function giveTagged(string $tag): void
    {
        $this->give(function (Container $container) use ($tag) {
            $tagged = $container->tagged($tag);

            if (is_array($tagged)) {
                return $tagged;
            }

            return iterator_to_array($tagged);
        });
    }

    public function giveConfig(string $key, mixed $default = null): void
    {
        $this->give(function (Container $container) use ($key, $default) {
            $config = $container->make('config');

            if (is_object($config) && method_exists($config, 'get')) {
                return $config->get($key, $default);
            }

            return $default;
        });
    }
}
