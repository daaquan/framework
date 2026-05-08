<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class RouteParameter implements ContextualAttribute
{
    public function __construct(public string $name) {}

    public static function resolve(self $attribute, Container $container): mixed
    {
        if (!$container->has('routeParams')) {
            return null;
        }

        $params = $container->make('routeParams');
        if (!is_array($params)) {
            return null;
        }

        return $params[$attribute->name] ?? null;
    }
}
