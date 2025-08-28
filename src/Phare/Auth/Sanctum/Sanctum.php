<?php

namespace Phare\Auth\Sanctum;

use DateTimeInterface;

class Sanctum
{
    public static string $personalAccessTokenModel = PersonalAccessToken::class;

    public static array $permissions = [];

    public static ?DateTimeInterface $expiration = null;

    public static bool $ignoreMigrations = false;

    public static function usePersonalAccessTokenModel(string $model): void
    {
        static::$personalAccessTokenModel = $model;
    }

    public static function personalAccessTokenModel(): string
    {
        return static::$personalAccessTokenModel;
    }

    public static function actingAs(mixed $user, array $abilities = ['*'], ?string $guard = null): mixed
    {
        $token = new PersonalAccessToken();
        $token->forceFill([
            'tokenable_id' => $user->getKey(),
            'tokenable_type' => get_class($user),
            'name' => 'test-token',
            'abilities' => $abilities,
        ]);

        $user->withAccessToken($token);

        if ($guard) {
            app('auth')->guard($guard)->setUser($user);
        } else {
            app('auth')->setUser($user);
        }

        return $user;
    }

    public static function createToken(mixed $user, string $name, array $abilities = ['*']): NewAccessToken
    {
        $token = $user->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken = static::generateTokenString()),
            'abilities' => $abilities,
        ]);

        return new NewAccessToken($token, $token->getKey() . '|' . $plainTextToken);
    }

    protected static function generateTokenString(): string
    {
        return sprintf(
            '%s%s%s',
            config('app.key'),
            time(),
            bin2hex(random_bytes(32))
        );
    }

    public static function findToken(string $token): ?PersonalAccessToken
    {
        if (strpos($token, '|') === false) {
            return static::$personalAccessTokenModel::where('token', hash('sha256', $token))->first();
        }

        [$id, $token] = explode('|', $token, 2);

        if ($instance = static::$personalAccessTokenModel::find($id)) {
            return hash_equals($instance->token, hash('sha256', $token)) ? $instance : null;
        }

        return null;
    }

    public static function hasValidToken(mixed $user, string $token): bool
    {
        $accessToken = static::findToken($token);

        return $accessToken &&
            $accessToken->tokenable_id == $user->getKey() &&
            $accessToken->tokenable_type == get_class($user);
    }

    public static function ignoreMigrations(): void
    {
        static::$ignoreMigrations = true;
    }

    public static function defaultTokenExpiration(?DateTimeInterface $expiration): void
    {
        static::$expiration = $expiration;
    }
}
