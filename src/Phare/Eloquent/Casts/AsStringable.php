<?php

namespace Phare\Eloquent\Casts;

class AsStringable implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?object
    {
        if ($value === null) {
            return null;
        }

        return new class((string)$value) implements \Stringable
        {
            public function __construct(private string $value) {}

            public function __toString(): string
            {
                return $this->value;
            }
        };
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string)$value;
    }
}
