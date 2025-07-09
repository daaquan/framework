<?php

namespace Tests\Mock\Models;

use Phare\Auth\Authenticatable;
use Phare\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Phare\Eloquent\Concerns\HasTimestamps;
use Phare\Eloquent\Concerns\SoftDeletes;
use Phare\Eloquent\Model;

/**
 * @property int $id
 * @property string $device_id
 * @property string $name
 * @property string $email
 * @property \DateTime $email_verified_at
 * @property string $password
 * @property \DateTime $birthday
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime $deleted_at
 */
class User extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasTimestamps;
    use SoftDeletes;

    protected ?string $connection = 'db';

    protected array $fillable = [
        'id',
        'device_id',
        'name',
        'email',
        'email_verified_at',
        'password',
        'birthday',
    ];

    protected array $hidden = [
        'password',
    ];

    protected array $passwordAttributes = [
        'password',
    ];

    protected array $casts = [
        'id' => 'int',
        'email_verified_at' => 'datetime',
        'birthday' => 'date',
    ];
}
