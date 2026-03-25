<?php

use Phare\Auth\Sanctum\PersonalAccessToken;

class PersonalAccessTokenBehaviorTestToken extends PersonalAccessToken
{
    public array $abilities = [];

    public mixed $expires_at = null;
}

function makePersonalAccessTokenBehaviorTestToken(): PersonalAccessTokenBehaviorTestToken
{
    /** @var PersonalAccessTokenBehaviorTestToken $token */
    $token = (new ReflectionClass(PersonalAccessTokenBehaviorTestToken::class))->newInstanceWithoutConstructor();
    $token->abilities = [];
    $token->expires_at = null;

    return $token;
}

test('personal access token can check ability', function () {
    $token = makePersonalAccessTokenBehaviorTestToken();
    $token->abilities = ['read', 'write'];

    expect($token->can('read'))->toBeTrue();
    expect($token->can('write'))->toBeTrue();
    expect($token->can('delete'))->toBeFalse();
});

test('personal access token with wildcard can do anything', function () {
    $token = makePersonalAccessTokenBehaviorTestToken();
    $token->abilities = ['*'];

    expect($token->can('read'))->toBeTrue();
    expect($token->can('write'))->toBeTrue();
    expect($token->can('delete'))->toBeTrue();
});

test('personal access token cant and cannot methods work', function () {
    $token = makePersonalAccessTokenBehaviorTestToken();
    $token->abilities = ['read'];

    expect($token->cant('read'))->toBeFalse();
    expect($token->cant('write'))->toBeTrue();
    expect($token->cannot('write'))->toBeTrue();
});

test('personal access token checks expiration', function () {
    $expiredToken = makePersonalAccessTokenBehaviorTestToken();
    $expiredToken->expires_at = new DateTime('yesterday');

    $validToken = makePersonalAccessTokenBehaviorTestToken();
    $validToken->expires_at = new DateTime('tomorrow');

    $neverExpiresToken = makePersonalAccessTokenBehaviorTestToken();
    $neverExpiresToken->expires_at = null;

    expect($expiredToken->isExpired())->toBeTrue();
    expect($validToken->isExpired())->toBeFalse();
    expect($neverExpiresToken->isExpired())->toBeFalse();
});
