<?php

use Phare\Auth\Passwords\PasswordBroker;
use Phare\Auth\Passwords\TokenRepositoryInterface;
use Phare\Contracts\Auth\CanResetPassword;
use Phare\Hashing\BcryptHasher;

function makeResettable(string $email): CanResetPassword
{
    return new class($email) implements CanResetPassword
    {
        public function __construct(private string $email) {}

        public function getEmailForPasswordReset(): string
        {
            return $this->email;
        }
    };
}

/** In-memory token repository so the broker can be tested without a database. */
class ArrayTokenRepository implements TokenRepositoryInterface
{
    /** @var array<string, array{token: string, created_at: string}> */
    public array $store = [];

    public function create(string $email, string $hashedToken): void
    {
        $this->store[$email] = ['token' => $hashedToken, 'created_at' => date('Y-m-d H:i:s')];
    }

    public function find(string $email): ?array
    {
        return $this->store[$email] ?? null;
    }

    public function delete(string $email): void
    {
        unset($this->store[$email]);
    }
}

beforeEach(function () {
    $this->tokens = new ArrayTokenRepository();
    // rounds=4: fast bcrypt for tests only
    $this->broker = new PasswordBroker($this->tokens, new BcryptHasher(['rounds' => 4]), 60);
});

test('stored token is hashed, not plaintext', function () {
    $plain = $this->broker->createToken(makeResettable('a@example.com'));

    $stored = $this->tokens->store['a@example.com']['token'];

    expect($stored)->not->toBe($plain);
    expect(password_verify($plain, $stored))->toBeTrue();
});

test('validateToken accepts the correct plaintext token', function () {
    $plain = $this->broker->createToken(makeResettable('a@example.com'));

    expect($this->broker->validateToken('a@example.com', $plain))->toBeTrue();
});

test('validateToken rejects a wrong token', function () {
    $this->broker->createToken(makeResettable('a@example.com'));

    expect($this->broker->validateToken('a@example.com', 'wrong-token'))->toBeFalse();
});

test('validateToken rejects an unknown email', function () {
    expect($this->broker->validateToken('nobody@example.com', 'whatever'))->toBeFalse();
});

test('validateToken rejects an expired token', function () {
    $plain = $this->broker->createToken(makeResettable('a@example.com'));

    // Backdate created_at past the 60-minute window
    $this->tokens->store['a@example.com']['created_at'] = date('Y-m-d H:i:s', time() - 3601);

    expect($this->broker->validateToken('a@example.com', $plain))->toBeFalse();
});

test('createToken replaces any existing token for the email', function () {
    $this->broker->createToken(makeResettable('a@example.com'));
    $second = $this->broker->createToken(makeResettable('a@example.com'));

    expect($this->broker->validateToken('a@example.com', $second))->toBeTrue();
});
