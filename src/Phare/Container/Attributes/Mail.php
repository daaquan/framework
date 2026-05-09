<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Mail implements ContextualAttribute
{
    public function __construct(public ?string $mailer = null) {}

    public static function resolve(self $attribute, Container $container): mixed
    {
        if ($attribute->mailer === null) {
            return $container->make('mailer');
        }

        return $container->make('mail.manager')->mailer($attribute->mailer);
    }
}
