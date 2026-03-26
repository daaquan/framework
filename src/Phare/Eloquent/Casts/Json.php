<?php

namespace Phare\Eloquent\Casts;

class Json
{
    public static function decode(mixed $value, bool $associative = true): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return $value;
        }

        return json_decode((string)$value, $associative, 512, JSON_THROW_ON_ERROR);
    }

    public static function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
