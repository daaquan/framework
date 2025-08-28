<?php

use Phare\Auth\Sanctum\HasApiTokens;
use Phare\Auth\Sanctum\NewAccessToken;
use Phare\Auth\Sanctum\PersonalAccessToken;

class TestUser
{
    use HasApiTokens;

    public function getKey()
    {
        return 1;
    }
}

test('user can create token', function () {
    $user = new TestUser();

    $user->shouldReceive('tokens')
        ->andReturn($tokensRelation = Mockery::mock('TokensRelation'));

    $token = new PersonalAccessToken([
        'name' => 'test-token',
        'token' => 'hashed-token',
        'abilities' => ['*'],
    ]);
    $token->id = 1;

    $tokensRelation->shouldReceive('create')
        ->once()
        ->andReturn($token);

    $newToken = $user->createToken('test-token');

    expect($newToken)->toBeInstanceOf(NewAccessToken::class);
    expect($newToken->accessToken)->toBe($token);
});

test('user can set and get current access token', function () {
    $user = new TestUser();
    $token = new PersonalAccessToken();

    $user->withAccessToken($token);

    expect($user->currentAccessToken())->toBe($token);
});

test('user can check token abilities', function () {
    $user = new TestUser();
    $token = new PersonalAccessToken([
        'abilities' => ['read', 'write'],
    ]);

    $user->withAccessToken($token);

    expect($user->tokenCan('read'))->toBeTrue();
    expect($user->tokenCan('delete'))->toBeFalse();
    expect($user->tokenCant('delete'))->toBeTrue();
});

test('user without token cannot do anything', function () {
    $user = new TestUser();

    expect($user->tokenCan('read'))->toBeFalse();
    expect($user->tokenCant('read'))->toBeTrue();
});

test('user can create plain text token', function () {
    $user = new TestUser();

    $user->shouldReceive('tokens')
        ->andReturn($tokensRelation = Mockery::mock('TokensRelation'));

    $tokensRelation->shouldReceive('create')
        ->once()
        ->andReturn(new PersonalAccessToken());

    $plainToken = $user->createPlainTextToken('test-token');

    expect($plainToken)->toBeString();
});

test('user can revoke current token', function () {
    $user = new TestUser();
    $token = Mockery::mock(PersonalAccessToken::class);

    $token->shouldReceive('delete')->once();

    $user->withAccessToken($token);
    $user->revokeCurrentToken();

    expect(true)->toBeTrue(); // Test passed if no exception
});

test('user can revoke all tokens', function () {
    $user = new TestUser();

    $user->shouldReceive('tokens')
        ->andReturn($tokensRelation = Mockery::mock('TokensRelation'));

    $tokensRelation->shouldReceive('delete')->once();

    $user->revokeAllTokens();

    expect(true)->toBeTrue(); // Test passed if no exception
});

test('user can revoke tokens except current', function () {
    $user = new TestUser();
    $currentToken = new PersonalAccessToken(['id' => 1]);

    $user->shouldReceive('tokens')
        ->andReturn($tokensRelation = Mockery::mock('TokensRelation'));

    $tokensRelation->shouldReceive('where')
        ->with('id', '!=', 1)
        ->andReturn($query = Mockery::mock('Query'));

    $query->shouldReceive('delete')->once();

    $user->withAccessToken($currentToken);
    $user->revokeTokensExceptCurrent();

    expect(true)->toBeTrue(); // Test passed if no exception
});
