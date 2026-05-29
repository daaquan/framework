<?php

use Phare\Auth\Manager;
use Phare\Auth\Sanctum\NewAccessToken;
use Phare\Auth\Sanctum\PersonalAccessToken;
use Phare\Auth\Sanctum\Sanctum;
use Phare\Auth\Sanctum\SanctumGuard;
use Phare\Encryption\Encrypter;
use Phare\Security\Csrf;

function paramHasSensitive(string $class, string $method, string $param): bool
{
    $rm = new ReflectionMethod($class, $method);

    foreach ($rm->getParameters() as $p) {
        if ($p->getName() === $param) {
            return count($p->getAttributes(SensitiveParameter::class)) > 0;
        }
    }

    return false;
}

it('marks secret-bearing parameters with #[\\SensitiveParameter]', function () {
    $cases = [
        [Encrypter::class, '__construct', 'key'],
        [Encrypter::class, 'encrypt', 'value'],
        [Encrypter::class, 'encryptString', 'value'],
        [Manager::class, 'attempt', 'credentials'],
        [Manager::class, 'validate', 'credentials'],
        [SanctumGuard::class, 'validate', 'credentials'],
        [SanctumGuard::class, 'findAccessToken', 'token'],
        [PersonalAccessToken::class, 'findToken', 'token'],
        [Sanctum::class, 'findToken', 'token'],
        [Sanctum::class, 'hasValidToken', 'token'],
        [NewAccessToken::class, '__construct', 'plainTextToken'],
        [Csrf::class, 'verifyToken', 'token'],
        [Csrf::class, 'storeToken', 'token'],
    ];

    foreach ($cases as [$class, $method, $param]) {
        expect(paramHasSensitive($class, $method, $param))
            ->toBeTrue("{$class}::{$method}(\${$param})");
    }
});
