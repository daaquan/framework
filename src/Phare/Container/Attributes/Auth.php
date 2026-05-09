<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Auth implements ContextualAttribute
{
    public function __construct(public ?string $guard = null) {}

    public static function resolve(self $attribute, Container $container): mixed
    {
        if ($attribute->guard === null) {
            return $container->make('auth');
        }

        return $container->make('auth.manager')->guard($attribute->guard);
    }
}
