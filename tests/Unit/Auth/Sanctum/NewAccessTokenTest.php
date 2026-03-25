<?php

use Phare\Auth\Sanctum\NewAccessToken;
use Phare\Auth\Sanctum\PersonalAccessToken;

class NewAccessTokenTestToken extends PersonalAccessToken
{
}

function makeNewAccessTokenTestToken(): NewAccessTokenTestToken
{
    /** @var NewAccessTokenTestToken $token */
    $token = (new ReflectionClass(NewAccessTokenTestToken::class))->newInstanceWithoutConstructor();

    return $token;
}

test('new access token can be created', function () {
    $accessToken = makeNewAccessTokenTestToken();
    $plainTextToken = 'plain-text-token';

    $newToken = new NewAccessToken($accessToken, $plainTextToken);

    expect($newToken->accessToken)->toBe($accessToken);
    expect($newToken->plainTextToken)->toBe($plainTextToken);
});

test('new access token can be converted to array', function () {
    $accessToken = makeNewAccessTokenTestToken();
    $plainTextToken = 'plain-text-token';

    $newToken = new NewAccessToken($accessToken, $plainTextToken);
    $array = $newToken->toArray();

    expect($array)->toHaveKey('accessToken', $accessToken);
    expect($array)->toHaveKey('plainTextToken', $plainTextToken);
});

test('new access token can be converted to string', function () {
    $accessToken = makeNewAccessTokenTestToken();
    $plainTextToken = 'plain-text-token';

    $newToken = new NewAccessToken($accessToken, $plainTextToken);

    expect((string) $newToken)->toBe($plainTextToken);
    expect($newToken->__toString())->toBe($plainTextToken);
});
