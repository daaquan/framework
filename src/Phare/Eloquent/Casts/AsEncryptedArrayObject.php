<?php

namespace Phare\Eloquent\Casts;

class AsEncryptedArrayObject implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?\ArrayObject
    {
        if ($value === null) {
            return null;
        }

        $decoded = Json::decode(decrypt((string)$value), true);

        return $decoded === null ? null : new \ArrayObject((array)$decoded, \ArrayObject::ARRAY_AS_PROPS);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value instanceof \ArrayObject) {
            $value = $value->getArrayCopy();
        }

        $json = Json::encode($value);

        return $json === null ? null : encrypt($json);
    }
}
