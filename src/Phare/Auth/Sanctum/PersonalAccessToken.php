<?php

namespace Phare\Auth\Sanctum;

use Phare\Eloquent\Model;

class PersonalAccessToken extends Model
{
    protected string $table = 'personal_access_tokens';

    protected array $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'last_used_at',
    ];

    protected array $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected array $hidden = [
        'token',
    ];

    public function tokenable()
    {
        return $this->morphTo();
    }

    public function findToken(string $token): ?static
    {
        if (str_contains($token, '|')) {
            [$id, $token] = explode('|', $token, 2);

            if ($instance = $this->find($id)) {
                return hash_equals($instance->token, hash('sha256', $token)) ? $instance : null;
            }
        }

        return $this->where('token', hash('sha256', $token))->first();
    }

    public function can(string $ability): bool
    {
        return in_array('*', $this->abilities) ||
               array_key_exists($ability, array_flip($this->abilities));
    }

    public function cant(string $ability): bool
    {
        return !$this->can($ability);
    }

    public function cannot(string $ability): bool
    {
        return $this->cant($ability);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($token) {
            if (!$token->token) {
                $token->token = hash('sha256', bin2hex(random_bytes(40)));
            }
        });
    }

    public function isExpired(): bool
    {
        if (is_null($this->expires_at)) {
            return false;
        }

        $expiresAt = $this->expires_at;
        if ($expiresAt instanceof \DateTime) {
            return $expiresAt < new \DateTime();
        }

        // Assuming Carbon-like interface
        return method_exists($expiresAt, 'isPast') ? $expiresAt->isPast() : false;
    }

    public function touch(?string $attribute = null): bool
    {
        $this->forceFill(['last_used_at' => new \DateTime()]);

        return parent::touch($attribute);
    }
}
