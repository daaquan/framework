<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Cache implements ContextualAttribute
{
    public function __construct(public ?string $store = null) {}

    public static function resolve(self $attribute, Container $container): mixed
    {
        if ($attribute->store === null) {
            return $container->make('cache');
        }

        return $container->make('cache.manager')->store($attribute->store);
    }
}
