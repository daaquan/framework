<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Tag implements ContextualAttribute
{
    public function __construct(public string $tag)
    {
    }

    public static function resolve(self $attribute, Container $container): iterable
    {
        return $container->tagged($attribute->tag);
    }
}
