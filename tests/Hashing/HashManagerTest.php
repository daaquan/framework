<?php

use Phare\Hashing\HasherInterface;
use Phare\Hashing\HashManager;

beforeEach(function () {
    $this->hashManager = new HashManager();
});

it('creates bcrypt hashes by default', function () {
    $hash = $this->hashManager->make('password');

    expect(password_get_info($hash)['algoName'])->toBe('bcrypt');
    expect($this->hashManager->check('password', $hash))->toBeTrue();
    expect($this->hashManager->check('wrong-password', $hash))->toBeFalse();
});

it('supports different hash drivers', function () {
    $bcryptHash = $this->hashManager->driver('bcrypt')->make('password');

    expect($this->hashManager->check('password', $bcryptHash))->toBeTrue();
    expect(password_get_info($bcryptHash)['algoName'])->toBe('bcrypt');
});

it('supports argon2i hashing', function () {
    if (!defined('PASSWORD_ARGON2I')) {
        $this->markTestSkipped('Argon2i not supported on this system');
    }

    $hash = $this->hashManager->driver('argon2i')->make('password');

    expect(password_get_info($hash)['algoName'])->toBe('argon2i');
    expect($this->hashManager->driver('argon2i')->check('password', $hash))->toBeTrue();
});

it('supports argon2id hashing', function () {
    if (!defined('PASSWORD_ARGON2ID')) {
        $this->markTestSkipped('Argon2id not supported on this system');
    }

    $hash = $this->hashManager->driver('argon2id')->make('password');

    expect(password_get_info($hash)['algoName'])->toBe('argon2id');
    expect($this->hashManager->driver('argon2id')->check('password', $hash))->toBeTrue();
});

it('checks if rehashing is needed', function () {
    $hash = $this->hashManager->make('password', ['rounds' => 4]);

    expect($this->hashManager->needsRehash($hash, ['rounds' => 10]))->toBeTrue();
    expect($this->hashManager->needsRehash($hash, ['rounds' => 4]))->toBeFalse();
});

it('provides hash information', function () {
    $hash = $this->hashManager->make('password');
    $info = $this->hashManager->info($hash);

    expect($info['algoName'])->toBe('bcrypt');
    expect($info['options']['cost'])->toBe(10);
});

it('throws exception for unknown driver', function () {
    expect(fn () => $this->hashManager->driver('unknown'))
        ->toThrow(\InvalidArgumentException::class);
});

it('allows extending with custom hashers', function () {
    $customHasher = new class() implements HasherInterface
    {
        public function make(string $value, array $options = []): string
        {
            return md5($value);
        }

        public function check(string $value, string $hashedValue, array $options = []): bool
        {
            return md5($value) === $hashedValue;
        }

        public function needsRehash(string $hashedValue, array $options = []): bool
        {
            return false;
        }

        public function info(string $hashedValue): array
        {
            return ['algo' => 'md5'];
        }
    };

    $this->hashManager->extend('md5', $customHasher);

    $hash = $this->hashManager->driver('md5')->make('password');
    expect($hash)->toBe(md5('password'));
    expect($this->hashManager->driver('md5')->check('password', $hash))->toBeTrue();
});

it('can change default driver', function () {
    if (!defined('PASSWORD_ARGON2I')) {
        $this->markTestSkipped('Argon2i not supported on this system');
    }

    $this->hashManager->setDefaultDriver('argon2i');

    $hash = $this->hashManager->make('password');
    expect(password_get_info($hash)['algoName'])->toBe('argon2i');
    expect($this->hashManager->getDefaultDriver())->toBe('argon2i');
});

it('delegates methods to default driver', function () {
    $hash = $this->hashManager->make('password');

    expect($this->hashManager->check('password', $hash))->toBeTrue();
    expect($this->hashManager->needsRehash($hash))->toBeFalse();
    expect($this->hashManager->info($hash)['algoName'])->toBe('bcrypt');
});

it('passes options to hasher', function () {
    $hash = $this->hashManager->make('password', ['rounds' => 12]);
    $info = $this->hashManager->info($hash);

    expect($info['options']['cost'])->toBe(12);
});

it('handles empty passwords gracefully', function () {
    $hash = $this->hashManager->make('');

    expect($this->hashManager->check('', $hash))->toBeTrue();
    expect($this->hashManager->check('not-empty', $hash))->toBeFalse();
});

it('rejects check on empty hashes', function () {
    expect($this->hashManager->check('password', ''))->toBeFalse();
});
