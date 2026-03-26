<?php

namespace Tests\Mock\Models;

use Phare\Eloquent\Model;

class Video extends Model
{
    protected ?string $connection = 'db';

    protected ?string $table = 'videos';

    protected array $fillable = [
        'id',
        'title',
    ];

    protected array $casts = [
        'id' => 'int',
    ];

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
