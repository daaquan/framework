<?php

use Phalcon\Di\Di;
use Phalcon\Config\Config as PhalconConfig;
use Phare\Auth\Sanctum\NewAccessToken;
use Phare\Auth\Sanctum\PersonalAccessToken;
use Phare\Auth\Sanctum\Sanctum;
use Phare\Contracts\Foundation\Application as ApplicationContract;
use Tests\Support\SimpleApplication;

class SanctumTestToken extends PersonalAccessToken
{
    public int $id = 1;
    public string $token = '';
    public int $tokenable_id = 0;
    public string $tokenable_type = '';
    public array $abilities = ['*'];

    public function getKey()
    {
        return $this->id;
    }

    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->{$key} = $value;
        }

        return $this;
    }
}

class SanctumActingAsToken
{
    public int $tokenable_id = 0;
    public string $tokenable_type = '';
    public string $name = '';
    public array $abilities = ['*'];

    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->{$key} = $value;
        }

        return $this;
    }
}

class SanctumTestRelation
{
    public ?array $createdPayload = null;

    public function __construct(private SanctumTestToken $token) {}

    public function create(array $payload): SanctumTestToken
    {
        $this->createdPayload = $payload;

        return $this->token;
    }
}

class SanctumTestUser
{
    public mixed $accessToken = null;

    public function __construct(private int $id, private SanctumTestRelation $relation) {}

    public function getKey(): int
    {
        return $this->id;
    }

    public function tokens(): SanctumTestRelation
    {
        return $this->relation;
    }

    public function withAccessToken(mixed $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }
}

function makeSanctumTestToken(): SanctumTestToken
{
    /** @var SanctumTestToken $token */
    $token = (new ReflectionClass(SanctumTestToken::class))->newInstanceWithoutConstructor();
    $token->id = 1;
    $token->token = '';
    $token->tokenable_id = 0;
    $token->tokenable_type = '';
    $token->abilities = ['*'];

    return $token;
}

class SanctumStaticModel
{
    public static ?PersonalAccessToken $whereFirst = null;
    public static ?PersonalAccessToken $findResult = null;
    public static array $lastWhereArgs = [];
    public static mixed $lastFindId = null;

    public static function where(...$args)
    {
        self::$lastWhereArgs = $args;

        return new class()
        {
            public function first(): ?PersonalAccessToken
            {
                return SanctumStaticModel::$whereFirst;
            }
        };
    }

    public static function find($id): ?PersonalAccessToken
    {
        self::$lastFindId = $id;

        return self::$findResult;
    }
}

beforeEach(function () {
    Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    Sanctum::$expiration = null;
    Sanctum::$ignoreMigrations = false;

    SanctumStaticModel::$whereFirst = null;
    SanctumStaticModel::$findResult = null;
    SanctumStaticModel::$lastWhereArgs = [];
    SanctumStaticModel::$lastFindId = null;

    Di::reset();
    $di = new Di();
    Di::setDefault($di);

    $app = new SimpleApplication();
    $app->instance('config', new PhalconConfig([
        'app' => [
            'key' => 'testing-app-key',
        ],
    ]));

    $di->setShared(ApplicationContract::class, $app);
});

afterEach(function () {
    Di::reset();
    Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
});

test('sanctum can set personal access token model', function () {
    Sanctum::usePersonalAccessTokenModel(SanctumStaticModel::class);

    expect(Sanctum::personalAccessTokenModel())->toBe(SanctumStaticModel::class);
});

test('sanctum can create token', function () {
    $token = makeSanctumTestToken();
    $relation = new SanctumTestRelation($token);
    $user = new SanctumTestUser(1, $relation);

    $newToken = Sanctum::createToken($user, 'test-token');

    expect($newToken)->toBeInstanceOf(NewAccessToken::class);
    expect($newToken->accessToken)->toBe($token);
    expect($relation->createdPayload)->toBeArray();
    expect($relation->createdPayload['name'])->toBe('test-token');
    expect($relation->createdPayload['abilities'])->toBe(['*']);
    expect($relation->createdPayload['token'])->toBeString();
    expect($newToken->plainTextToken)->toStartWith('1|');
});

test('sanctum can find token without id prefix', function () {
    $token = makeSanctumTestToken();
    $token->token = hash('sha256', 'plain-text-token');

    SanctumStaticModel::$whereFirst = $token;
    Sanctum::usePersonalAccessTokenModel(SanctumStaticModel::class);

    $foundToken = Sanctum::findToken('plain-text-token');

    expect($foundToken)->toBe($token);
    expect(SanctumStaticModel::$lastWhereArgs)->toBe(['token', hash('sha256', 'plain-text-token')]);
});

test('sanctum can find token with pipe format', function () {
    $token = makeSanctumTestToken();
    $token->token = hash('sha256', 'plain-text-token');

    SanctumStaticModel::$findResult = $token;
    Sanctum::usePersonalAccessTokenModel(SanctumStaticModel::class);

    $foundToken = Sanctum::findToken('1|plain-text-token');

    expect($foundToken)->toBe($token);
    expect(SanctumStaticModel::$lastFindId)->toBe('1');
});

test('sanctum validates token for user', function () {
    $user = new SanctumTestUser(5, new SanctumTestRelation(makeSanctumTestToken()));

    $token = makeSanctumTestToken();
    $token->tokenable_id = 5;
    $token->tokenable_type = SanctumTestUser::class;

    SanctumStaticModel::$whereFirst = $token;
    Sanctum::usePersonalAccessTokenModel(SanctumStaticModel::class);

    expect(Sanctum::hasValidToken($user, 'plain-text-token'))->toBeTrue();
});

test('sanctum actingAs sets user with token on auth service', function () {
    $di = Di::getDefault();
    expect($di)->not->toBeNull();

    $auth = new class
    {
        public mixed $user = null;

        public function setUser(mixed $user): void
        {
            $this->user = $user;
        }
    };

    $app = $di->getShared(ApplicationContract::class);
    $app->instance('auth', $auth);

    Sanctum::usePersonalAccessTokenModel(SanctumActingAsToken::class);

    $user = new SanctumTestUser(11, new SanctumTestRelation(makeSanctumTestToken()));

    $result = Sanctum::actingAs($user, ['read', 'write']);

    expect($result)->toBe($user);
    expect($auth->user)->toBe($user);
    expect($user->accessToken)->toBeInstanceOf(SanctumActingAsToken::class);
    expect($user->accessToken->abilities)->toBe(['read', 'write']);
});

test('sanctum can ignore migrations', function () {
    Sanctum::ignoreMigrations();

    expect(Sanctum::$ignoreMigrations)->toBeTrue();
});

test('sanctum can set default expiration', function () {
    $expiration = new DateTimeImmutable('+30 days');

    Sanctum::defaultTokenExpiration($expiration);

    expect(Sanctum::$expiration)->toBe($expiration);
});
