<?php

namespace Tests\Mock\Models;

use Phare\Eloquent\Model;

class Role extends Model
{
    protected ?string $connection = 'db';

    protected ?string $table = 'roles';

    protected array $fillable = [
        'id',
        'name',
    ];

    protected array $casts = [
        'id' => 'int',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('active')
            ->withTimestamps();
    }
}
