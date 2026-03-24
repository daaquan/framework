<?php

namespace Phare\Contracts\Http\Validation;

interface Validator
{
    public static function make(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ): self;

    public function passes(): bool;

    public function fails(): bool;

    public function errors(): \Phare\Validation\MessageBag;

    public function validated(): array;

    public function safe(): array;
}
