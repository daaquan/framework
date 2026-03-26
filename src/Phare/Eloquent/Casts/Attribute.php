<?php

namespace Phare\Eloquent\Casts;

use Closure;

class Attribute
{
    public function __construct(
        public readonly ?Closure $get = null,
        public readonly ?Closure $set = null,
        protected bool $withObjectCaching = true,
    ) {}

    public static function make(?Closure $get = null, ?Closure $set = null): static
    {
        return new static($get, $set);
    }

    public function withoutObjectCaching(): static
    {
        $clone = clone $this;
        $clone->withObjectCaching = false;

        return $clone;
    }

    public function shouldCache(): bool
    {
        return $this->withObjectCaching;
    }
}
