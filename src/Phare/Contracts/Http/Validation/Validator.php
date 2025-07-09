<?php

namespace Phare\Contracts\Http\Validation;

interface Validator
{
    public static function make($data, $rules = []);

    public function rules(): array;

    public function validate($data): bool;

    public function getMessages();
}
