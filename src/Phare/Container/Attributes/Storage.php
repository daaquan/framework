<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Storage implements ContextualAttribute
{
    public function __construct(public ?string $disk = null) {}

    public static function resolve(self $attribute, Container $container): mixed
    {
        if ($attribute->disk === null) {
            return $container->make('filesystem');
        }

        return $container->make('filesystem.manager')->disk($attribute->disk);
    }
}
