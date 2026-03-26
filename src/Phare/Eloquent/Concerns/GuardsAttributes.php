<?php

namespace Phare\Eloquent\Concerns;

trait GuardsAttributes
{
    protected array $fillable = [];

    protected array $guarded = ['*'];

    protected static bool $unguarded = false;

    public static function unguard(bool $disable = true): void
    {
        static::$unguarded = $disable;
    }

    public static function reguard(): void
    {
        static::$unguarded = false;
    }

    public function guard(array|string $attributes): static
    {
        $attributes = is_array($attributes) ? $attributes : [$attributes];
        $this->guarded = array_values(array_unique([...$this->guarded, ...$attributes]));

        return $this;
    }

    public function fillableFromArray(array $attributes): array
    {
        if (static::$unguarded) {
            return $attributes;
        }

        if ($this->getFillable() !== []) {
            return array_intersect_key($attributes, array_flip($this->getFillable()));
        }

        return array_filter(
            $attributes,
            fn ($value, string $key) => !$this->isGuarded($key),
            ARRAY_FILTER_USE_BOTH
        );
    }

    public function isFillable(string $key): bool
    {
        if (static::$unguarded) {
            return true;
        }

        if (in_array($key, $this->getFillable(), true)) {
            return true;
        }

        if ($this->getFillable() === [] && !$this->isGuarded($key)) {
            return true;
        }

        return false;
    }

    public function isGuarded(string $key): bool
    {
        $guarded = $this->getGuarded();

        return $guarded === ['*'] || in_array($key, $guarded, true);
    }

    public function totallyGuarded(): bool
    {
        return $this->getFillable() === [] && $this->getGuarded() === ['*'] && !static::$unguarded;
    }

    public function getFillable(): array
    {
        return $this->fillable;
    }

    public function getGuarded(): array
    {
        return $this->guarded;
    }
}
