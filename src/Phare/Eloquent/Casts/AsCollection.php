<?php

namespace Phare\Eloquent\Casts;

use Phare\Collections\Collection;

class AsCollection implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?Collection
    {
        $decoded = Json::decode($value, true);

        return $decoded === null ? null : new Collection((array)$decoded);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        return Json::encode($value);
    }
}
