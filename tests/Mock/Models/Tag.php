<?php

namespace Tests\Mock\Models;

use Phare\Eloquent\Model;

class Tag extends Model
{
    protected ?string $connection = 'db';

    protected ?string $table = 'tags';

    protected array $fillable = [
        'id',
        'name',
    ];

    protected array $casts = [
        'id' => 'int',
    ];

    public function posts()
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    public function videos()
    {
        return $this->morphedByMany(Video::class, 'taggable');
    }
}
