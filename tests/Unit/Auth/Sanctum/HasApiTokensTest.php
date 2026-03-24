<?php

use Phare\Auth\Sanctum\HasApiTokens;
use Phare\Auth\Sanctum\NewAccessToken;
use Phare\Auth\Sanctum\PersonalAccessToken;

class HasApiTokensTestToken extends PersonalAccessToken
{
    public int $id = 1;
    public array $abilities = ['*'];
    public bool $deleted = false;

    public function getKey()
    {
        return $this->id;
    }

    public function can(string $ability): bool
    {
        return in_array('*', $this->abilities, true) || in_array($ability, $this->abilities, true);
    }

    public function delete(): bool
    {
        $this->deleted = true;

        return true;
    }
}

function makeHasApiTokensTestToken(): HasApiTokensTestToken
{
    /** @var HasApiTokensTestToken $token */
    $token = (new ReflectionClass(HasApiTokensTestToken::class))->newInstanceWithoutConstructor();
    $token->id = 1;
    $token->abilities = ['*'];
    $token->deleted = false;

    return $token;
}

class HasApiTokensTestRelation
{
    public ?array $createdPayload = null;
    public bool $deleted = false;
    public ?array $whereArgs = null;

    public function __construct(private HasApiTokensTestToken $token) {}

    public function create(array $payload): HasApiTokensTestToken
    {
        $this->createdPayload = $payload;

        return $this->token;
    }

    public function delete(): void
    {
        $this->deleted = true;
    }

    public function where(string $column, string $operator, int $value): self
    {
        $this->whereArgs = [$column, $operator, $value];

        return $this;
    }
}

class HasApiTokensTestUser
{
    use HasApiTokens;

    public function __construct(private HasApiTokensTestRelation $relation) {}

    public function getKey()
    {
        return 1;
    }

    public function tokens(): HasApiTokensTestRelation
    {
        return $this->relation;
    }

    protected function generateTokenString(): string
    {
        return 'fixed-token-string';
    }
}

test('user can create token', function () {
    $token = makeHasApiTokensTestToken();
    $relation = new HasApiTokensTestRelation($token);
    $user = new HasApiTokensTestUser($relation);

    $newToken = $user->createToken('test-token');

    expect($newToken)->toBeInstanceOf(NewAccessToken::class);
    expect($newToken->accessToken)->toBe($token);
    expect($newToken->plainTextToken)->toBe('1|fixed-token-string');
    expect($relation->createdPayload)->toBeArray();
    expect($relation->createdPayload['name'])->toBe('test-token');
    expect($relation->createdPayload['abilities'])->toBe(['*']);
    expect($relation->createdPayload['token'])->toBe(hash('sha256', 'fixed-token-string'));
});

test('user can set and get current access token', function () {
    $token = makeHasApiTokensTestToken();
    $user = new HasApiTokensTestUser(new HasApiTokensTestRelation($token));

    $user->withAccessToken($token);

    expect($user->currentAccessToken())->toBe($token);
});

test('user can check token abilities', function () {
    $token = makeHasApiTokensTestToken();
    $token->abilities = ['read', 'write'];

    $user = new HasApiTokensTestUser(new HasApiTokensTestRelation($token));
    $user->withAccessToken($token);

    expect($user->tokenCan('read'))->toBeTrue();
    expect($user->tokenCan('delete'))->toBeFalse();
    expect($user->tokenCant('delete'))->toBeTrue();
});

test('user without token cannot do anything', function () {
    $token = makeHasApiTokensTestToken();
    $user = new HasApiTokensTestUser(new HasApiTokensTestRelation($token));

    expect($user->tokenCan('read'))->toBeFalse();
    expect($user->tokenCant('read'))->toBeTrue();
});

test('user can create plain text token', function () {
    $token = makeHasApiTokensTestToken();
    $relation = new HasApiTokensTestRelation($token);
    $user = new HasApiTokensTestUser($relation);

    $plainToken = $user->createPlainTextToken('test-token');

    expect($plainToken)->toBe('fixed-token-string');
    expect($relation->createdPayload)->toBeArray();
    expect($relation->createdPayload['token'])->toBe(hash('sha256', 'fixed-token-string'));
});

test('user can revoke current token', function () {
    $token = makeHasApiTokensTestToken();
    $user = new HasApiTokensTestUser(new HasApiTokensTestRelation($token));

    $user->withAccessToken($token);
    $user->revokeCurrentToken();

    expect($token->deleted)->toBeTrue();
});

test('user can revoke all tokens', function () {
    $token = makeHasApiTokensTestToken();
    $relation = new HasApiTokensTestRelation($token);
    $user = new HasApiTokensTestUser($relation);

    $user->revokeAllTokens();

    expect($relation->deleted)->toBeTrue();
});

test('user can revoke tokens except current', function () {
    $token = makeHasApiTokensTestToken();
    $token->id = 7;

    $relation = new HasApiTokensTestRelation($token);
    $user = new HasApiTokensTestUser($relation);

    $user->withAccessToken($token);
    $user->revokeTokensExceptCurrent();

    expect($relation->whereArgs)->toBe(['id', '!=', 7]);
    expect($relation->deleted)->toBeTrue();
});
