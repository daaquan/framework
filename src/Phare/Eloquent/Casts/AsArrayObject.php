<?php

namespace Phare\Eloquent\Casts;

class AsArrayObject implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?\ArrayObject
    {
        $decoded = Json::decode($value, true);

        return $decoded === null ? null : new \ArrayObject((array)$decoded, \ArrayObject::ARRAY_AS_PROPS);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value instanceof \ArrayObject) {
            $value = $value->getArrayCopy();
        }

        return Json::encode($value);
    }
}
