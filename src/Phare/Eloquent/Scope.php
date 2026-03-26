<?php

namespace Phare\Eloquent;

interface Scope
{
    public function apply(Builder $builder, Model $model): void;
}
