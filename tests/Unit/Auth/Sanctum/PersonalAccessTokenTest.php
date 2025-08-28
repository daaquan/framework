<?php

use Phare\Auth\Sanctum\PersonalAccessToken;

test('personal access token can check ability', function () {
    $token = new PersonalAccessToken([
        'abilities' => ['read', 'write'],
    ]);

    expect($token->can('read'))->toBeTrue();
    expect($token->can('write'))->toBeTrue();
    expect($token->can('delete'))->toBeFalse();
});

test('personal access token with wildcard can do anything', function () {
    $token = new PersonalAccessToken([
        'abilities' => ['*'],
    ]);

    expect($token->can('read'))->toBeTrue();
    expect($token->can('write'))->toBeTrue();
    expect($token->can('delete'))->toBeTrue();
});

test('personal access token cant method works', function () {
    $token = new PersonalAccessToken([
        'abilities' => ['read'],
    ]);

    expect($token->cant('read'))->toBeFalse();
    expect($token->cant('write'))->toBeTrue();
    expect($token->cannot('write'))->toBeTrue();
});

test('personal access token checks expiration', function () {
    $yesterday = new DateTime('yesterday');
    $tomorrow = new DateTime('tomorrow');

    $expiredToken = new PersonalAccessToken([
        'expires_at' => $yesterday,
    ]);

    $validToken = new PersonalAccessToken([
        'expires_at' => $tomorrow,
    ]);

    $neverExpiresToken = new PersonalAccessToken([
        'expires_at' => null,
    ]);

    expect($expiredToken->isExpired())->toBeTrue();
    expect($validToken->isExpired())->toBeFalse();
    expect($neverExpiresToken->isExpired())->toBeFalse();
});

test('personal access token finds token by hash', function () {
    $token = new PersonalAccessToken();
    $plainToken = 'plain-text-token';
    $hashedToken = hash('sha256', $plainToken);

    $token->shouldReceive('where')
        ->with('token', $hashedToken)
        ->andReturn($query = Mockery::mock('Query'));

    $query->shouldReceive('first')->andReturn($token);

    $foundToken = $token->findToken($plainToken);

    expect($foundToken)->toBe($token);
});

test('personal access token finds token with pipe format', function () {
    $token = new PersonalAccessToken([
        'id' => 1,
        'token' => hash('sha256', 'plain-text-token'),
    ]);

    $token->shouldReceive('find')
        ->with('1')
        ->andReturn($token);

    $foundToken = $token->findToken('1|plain-text-token');

    expect($foundToken)->toBe($token);
});

test('personal access token touch updates last used at', function () {
    $token = new PersonalAccessToken();

    $token->shouldReceive('forceFill')
        ->once()
        ->with(['last_used_at' => Mockery::type('DateTime')])
        ->andReturnSelf();

    $token->shouldReceive('save')->andReturn(true);

    $result = $token->touch();

    expect($result)->toBeTrue();
});
