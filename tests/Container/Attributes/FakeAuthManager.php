<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

class FakeAuthManager
{
    public ?object $userInstance = null;

    public function user(): ?object
    {
        return $this->userInstance;
    }
}
