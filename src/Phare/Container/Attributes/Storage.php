<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Storage implements ContextualAttribute
{
    public static function resolve(self $attribute, Container $container): mixed
    {
        return $container->make('filesystem');
    }
}
