<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Hash implements ContextualAttribute
{
    public function __construct(public ?string $driver = null) {}

    public static function resolve(self $attribute, Container $container): mixed
    {
        if ($attribute->driver === null) {
            return $container->make('hash');
        }

        return $container->make('hash.manager')->driver($attribute->driver);
    }
}
