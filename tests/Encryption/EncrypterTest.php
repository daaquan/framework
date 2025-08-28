<?php

use Phare\Encryption\DecryptException;
use Phare\Encryption\Encrypter;

beforeEach(function () {
    $this->key = str_repeat('a', 32); // 256-bit key for aes-256-cbc
    $this->encrypter = new Encrypter($this->key);
});

it('encrypts and decrypts strings', function () {
    $value = 'Hello World';
    $encrypted = $this->encrypter->encrypt($value);
    $decrypted = $this->encrypter->decrypt($encrypted);

    expect($encrypted)->not->toBe($value);
    expect($decrypted)->toBe($value);
});

it('encrypts and decrypts arrays and objects', function () {
    $value = ['name' => 'John', 'age' => 30];
    $encrypted = $this->encrypter->encrypt($value);
    $decrypted = $this->encrypter->decrypt($encrypted);

    expect($encrypted)->not->toBe(serialize($value));
    expect($decrypted)->toBe($value);
});

it('encrypts strings without serialization', function () {
    $value = 'Hello World';
    $encrypted = $this->encrypter->encryptString($value);
    $decrypted = $this->encrypter->decryptString($encrypted);

    expect($decrypted)->toBe($value);
});

it('generates different encrypted values for same input', function () {
    $value = 'Hello World';
    $encrypted1 = $this->encrypter->encrypt($value);
    $encrypted2 = $this->encrypter->encrypt($value);

    expect($encrypted1)->not->toBe($encrypted2);
    expect($this->encrypter->decrypt($encrypted1))->toBe($value);
    expect($this->encrypter->decrypt($encrypted2))->toBe($value);
});

it('throws exception for invalid key size', function () {
    $shortKey = 'short';

    expect(fn () => new Encrypter($shortKey))
        ->toThrow(\InvalidArgumentException::class);
});

it('throws exception for unsupported cipher', function () {
    expect(fn () => new Encrypter($this->key, 'unsupported-cipher'))
        ->toThrow(\InvalidArgumentException::class);
});

it('throws exception when decrypting invalid payload', function () {
    $invalidPayload = base64_encode('invalid-json');

    expect(fn () => $this->encrypter->decrypt($invalidPayload))
        ->toThrow(DecryptException::class);
});

it('throws exception when MAC verification fails', function () {
    $encrypted = $this->encrypter->encrypt('Hello World');
    $payload = json_decode(base64_decode($encrypted), true);
    $payload['mac'] = 'invalid-mac';
    $tamperedPayload = base64_encode(json_encode($payload));

    expect(fn () => $this->encrypter->decrypt($tamperedPayload))
        ->toThrow(DecryptException::class);
});

it('works with different cipher algorithms', function () {
    $key128 = str_repeat('a', 16);
    $encrypter128 = new Encrypter($key128, 'aes-128-cbc');

    $value = 'Hello World';
    $encrypted = $encrypter128->encrypt($value);
    $decrypted = $encrypter128->decrypt($encrypted);

    expect($decrypted)->toBe($value);
});

it('supports AEAD ciphers like AES-GCM', function () {
    if (!in_array('aes-256-gcm', openssl_get_cipher_methods())) {
        $this->markTestSkipped('AES-256-GCM not supported on this system');
    }

    $encrypter = new Encrypter($this->key, 'aes-256-gcm');

    $value = 'Hello World';
    $encrypted = $encrypter->encrypt($value);
    $decrypted = $encrypter->decrypt($encrypted);

    expect($decrypted)->toBe($value);
});

it('generates secure keys', function () {
    $key1 = Encrypter::generateKeyString();
    $key2 = Encrypter::generateKeyString();

    expect($key1)->not->toBe($key2);
    expect(strlen(base64_decode($key1)))->toBe(32); // 256-bit key
    expect(strlen(base64_decode($key2)))->toBe(32);
});

it('generates keys for different ciphers', function () {
    $key128 = Encrypter::generateKeyString('aes-128-cbc');
    $key256 = Encrypter::generateKeyString('aes-256-cbc');

    expect(strlen(base64_decode($key128)))->toBe(16); // 128-bit key
    expect(strlen(base64_decode($key256)))->toBe(32); // 256-bit key
});

it('returns correct key and cipher', function () {
    expect($this->encrypter->getKey())->toBe($this->key);
    expect($this->encrypter->getCipher())->toBe('aes-256-cbc');
});

it('handles empty strings', function () {
    $value = '';
    $encrypted = $this->encrypter->encrypt($value);
    $decrypted = $this->encrypter->decrypt($encrypted);

    expect($decrypted)->toBe($value);
});

it('handles null values through serialization', function () {
    $value = null;
    $encrypted = $this->encrypter->encrypt($value);
    $decrypted = $this->encrypter->decrypt($encrypted);

    expect($decrypted)->toBeNull();
});
