<?php

namespace Phare\Support\Facades;

/**
 * @method static \Phare\Contracts\Auth\Authenticatable user()
 * @method static bool guest()
 * @method static bool attempt(array $credentials = [])
 * @method static bool check()
 * @method static bool login(\Phare\Contracts\Auth\Authenticatable $user)
 * @method static void logout(): void
 * @method static mixed|null retrieveIdentifier()
 * @method static \Phare\Contracts\Auth\Authenticatable loginUsingId(int $id)
 */
class Auth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'auth';
    }
}
