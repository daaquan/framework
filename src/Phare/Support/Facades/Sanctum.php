<?php

namespace Phare\Support\Facades;

use Phare\Support\Facade;

/**
 * @method static void usePersonalAccessTokenModel(string $model)
 * @method static string personalAccessTokenModel()
 * @method static mixed actingAs(mixed $user, array $abilities = ['*'], string $guard = null)
 * @method static \Phare\Auth\Sanctum\NewAccessToken createToken(mixed $user, string $name, array $abilities = ['*'])
 * @method static \Phare\Auth\Sanctum\PersonalAccessToken|null findToken(string $token)
 * @method static bool hasValidToken(mixed $user, string $token)
 * @method static void ignoreMigrations()
 * @method static void defaultTokenExpiration(\DateTimeInterface $expiration = null)
 *
 * @see \Phare\Auth\Sanctum\Sanctum
 */
class Sanctum extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sanctum';
    }
}
