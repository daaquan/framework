<?php

namespace Phare\Support\Facades;

/**
 * @method static bool checkHash(string $password, string $passwordHash, int $maxPassLength = 0)
 * @method static bool checkToken(string $tokenKey = null, $tokenValue = null, bool $destroyIfValid = true)
 * @method static string computeHmac(string $data, string $key, string $algo, bool $raw = false)
 * @method static \Phalcon\Encryption\Security destroyToken()
 * @method static int getDefaultHash()
 * @method static array getHashInformation(string $hash)
 * @method static \Phalcon\Encryption\Security\Random getRandom()
 * @method static int getRandomBytes()
 * @method static string|null getRequestToken()
 * @method static string|null getSessionToken()
 * @method static string getSaltBytes(int $numberBytes = 0)
 * @method static string|null getToken()
 * @method static string|null getTokenKey()
 * @method static int getWorkFactor()
 * @method static string hash(string $password, array $options = [])
 * @method static bool isLegacyHash(string $passwordHash)
 * @method static \Phalcon\Encryption\Security setDefaultHash(int $defaultHash)
 * @method static \Phalcon\Encryption\Security setRandomBytes(int $randomBytes)
 * @method static \Phalcon\Encryption\Security setWorkFactor(int $workFactor)
 */
class Security extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'security';
    }
}
