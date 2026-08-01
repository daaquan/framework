<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Config implements ContextualAttribute
{
    public function __construct(
        public string $key,
        public mixed $default = null
    ) {}

    public static function resolve(self $attribute, Container $container): mixed
    {
        $config = $container->make('config');
        if (is_object($config) && method_exists($config, 'get')) {
            return $config->get($attribute->key, $attribute->default);
        }

        return $attribute->default;
    }
}
