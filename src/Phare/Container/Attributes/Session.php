<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Session implements ContextualAttribute
{
    public function __construct(public ?string $store = null) {}

    public static function resolve(self $attribute, Container $container): mixed
    {
        if ($attribute->store === null) {
            return $container->make('session');
        }

        return $container->make('session.manager')->store($attribute->store);
    }
}
