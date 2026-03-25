<?php

namespace Tests\Mock\Models;

use Phare\Eloquent\Model;

class Post extends Model
{
    protected ?string $connection = 'db';

    protected ?string $table = 'posts';

    protected array $fillable = [
        'id',
        'user_id',
        'title',
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
