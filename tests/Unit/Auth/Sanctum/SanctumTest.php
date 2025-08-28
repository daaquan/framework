<?php

use Phare\Auth\Sanctum\NewAccessToken;
use Phare\Auth\Sanctum\PersonalAccessToken;
use Phare\Auth\Sanctum\Sanctum;

test('sanctum can set personal access token model', function () {
    $model = 'App\\Models\\CustomToken';

    Sanctum::usePersonalAccessTokenModel($model);

    expect(Sanctum::personalAccessTokenModel())->toBe($model);
});

test('sanctum can generate token string', function () {
    $user = Mockery::mock('User');
    $user->shouldReceive('getKey')->andReturn(1);
    $user->shouldReceive('tokens')->andReturn(
        $tokensRelation = Mockery::mock('TokensRelation')
    );

    $token = new PersonalAccessToken([
        'name' => 'test-token',
        'token' => 'hashed-token',
        'abilities' => ['*'],
    ]);
    $token->id = 1;

    $tokensRelation->shouldReceive('create')
        ->once()
        ->andReturn($token);

    $newToken = Sanctum::createToken($user, 'test-token');

    expect($newToken)->toBeInstanceOf(NewAccessToken::class);
    expect($newToken->accessToken)->toBe($token);
});

test('sanctum can find token', function () {
    $token = new PersonalAccessToken([
        'id' => 1,
        'token' => hash('sha256', 'plain-text-token'),
        'abilities' => ['*'],
    ]);

    PersonalAccessToken::shouldReceive('where')
        ->with('token', hash('sha256', 'plain-text-token'))
        ->andReturn($query = Mockery::mock('Query'));

    $query->shouldReceive('first')->andReturn($token);

    $foundToken = Sanctum::findToken('plain-text-token');

    expect($foundToken)->toBe($token);
});

test('sanctum can find token with pipe format', function () {
    $token = new PersonalAccessToken([
        'id' => 1,
        'token' => hash('sha256', 'plain-text-token'),
        'abilities' => ['*'],
    ]);

    PersonalAccessToken::shouldReceive('find')
        ->with('1')
        ->andReturn($token);

    $foundToken = Sanctum::findToken('1|plain-text-token');

    expect($foundToken)->toBe($token);
});

test('sanctum validates token for user', function () {
    $user = Mockery::mock('User');
    $user->shouldReceive('getKey')->andReturn(1);

    $token = new PersonalAccessToken([
        'tokenable_id' => 1,
        'tokenable_type' => get_class($user),
        'token' => hash('sha256', 'plain-text-token'),
        'abilities' => ['*'],
    ]);

    Sanctum::shouldReceive('findToken')
        ->with('plain-text-token')
        ->andReturn($token);

    $isValid = Sanctum::hasValidToken($user, 'plain-text-token');

    expect($isValid)->toBeTrue();
});

test('sanctum acting as sets user with token', function () {
    $user = Mockery::mock('User');
    $user->shouldReceive('getKey')->andReturn(1);
    $user->shouldReceive('withAccessToken')->andReturnSelf();

    $auth = Mockery::mock('Auth');
    $auth->shouldReceive('setUser')->with($user);

    app()->instance('auth', $auth);

    $result = Sanctum::actingAs($user, ['read', 'write']);

    expect($result)->toBe($user);
});

test('sanctum can ignore migrations', function () {
    Sanctum::ignoreMigrations();

    expect(Sanctum::$ignoreMigrations)->toBeTrue();
});

test('sanctum can set default expiration', function () {
    $expiration = now()->addDays(30);

    Sanctum::defaultTokenExpiration($expiration);

    expect(Sanctum::$expiration)->toBe($expiration);
});
