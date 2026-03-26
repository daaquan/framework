<?php

namespace Phare\Eloquent\Casts;

interface CastsInboundAttributes
{
    public function set($model, string $key, $value, array $attributes);
}
