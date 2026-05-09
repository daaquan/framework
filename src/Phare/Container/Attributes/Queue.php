<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Queue implements ContextualAttribute
{
    public function __construct(public ?string $connection = null) {}

    public static function resolve(self $attribute, Container $container): mixed
    {
        if ($attribute->connection === null) {
            return $container->make('queue')->connection();
        }

        return $container->make('queue.manager')->connection($attribute->connection);
    }
}
