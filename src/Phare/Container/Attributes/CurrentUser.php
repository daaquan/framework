<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class CurrentUser implements ContextualAttribute
{
    public function __construct(public ?string $guard = null) {}

    public static function resolve(self $attribute, Container $container): mixed
    {
        $auth = $container->make('auth');

        if ($attribute->guard === null) {
            return $auth->user();
        }

        return $container->make('auth.manager')->guard($attribute->guard)->user();
    }
}
