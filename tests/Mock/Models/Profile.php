<?php

namespace Tests\Mock\Models;

use Phare\Eloquent\Model;

class Profile extends Model
{
    protected ?string $connection = 'db';

    protected ?string $table = 'profiles';

    protected array $fillable = [
        'id',
        'user_id',
        'bio',
    ];

    protected array $casts = [
        'id' => 'int',
        'user_id' => 'int',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
