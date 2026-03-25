<?php

namespace Tests\Mock\Models;

use Phare\Eloquent\Model;

class Label extends Model
{
    protected ?string $connection = 'db';

    protected ?string $table = 'labels';

    protected array $fillable = [
        'id',
        'labelable_id',
        'labelable_type',
        'name',
    ];

    protected array $casts = [
        'id' => 'int',
        'labelable_id' => 'int',
    ];

    public function labelable()
    {
        return $this->morphTo();
    }
}
