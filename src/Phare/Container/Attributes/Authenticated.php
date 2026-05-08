<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Auth\AuthenticationException;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Authenticated implements ContextualAttribute
{
    public static function resolve(self $attribute, Container $container): mixed
    {
        $user = $container->make('auth')->user();

        if ($user === null) {
            throw new AuthenticationException();
        }

        return $user;
    }
}
