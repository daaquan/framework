<?php

namespace Phare\Http\Resources;

class MergeValue
{
    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }
}
