<?php

namespace Phare\Eloquent\Casts;

use Phare\Collections\Collection;

class AsEncryptedCollection implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?Collection
    {
        if ($value === null) {
            return null;
        }

        $decoded = Json::decode(decrypt((string)$value), true);

        return $decoded === null ? null : new Collection((array)$decoded);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        $json = Json::encode($value);

        return $json === null ? null : encrypt($json);
    }
}
