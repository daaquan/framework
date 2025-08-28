<?php

namespace Phare\Encryption;

class Encrypter
{
    protected string $key;

    protected string $cipher;

    protected static array $supportedCiphers = [
        'aes-128-cbc' => ['size' => 16, 'aead' => false],
        'aes-256-cbc' => ['size' => 32, 'aead' => false],
        'aes-128-gcm' => ['size' => 16, 'aead' => true],
        'aes-256-gcm' => ['size' => 32, 'aead' => true],
    ];

    public function __construct(string $key, string $cipher = 'aes-256-cbc')
    {
        $this->validateKey($key, $cipher);

        $this->key = $key;
        $this->cipher = $cipher;
    }

    public function encrypt(mixed $value, bool $serialize = true): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));

        $value = $serialize ? serialize($value) : (string)$value;

        if ($this->isAEAD()) {
            $tag = null;
            $encrypted = openssl_encrypt($value, $this->cipher, $this->key, 0, $iv, $tag);
            $payload = base64_encode(json_encode([
                'iv' => base64_encode($iv),
                'value' => $encrypted,
                'tag' => base64_encode($tag),
                'mac' => '',
            ]));
        } else {
            $encrypted = openssl_encrypt($value, $this->cipher, $this->key, 0, $iv);
            $payload = base64_encode(json_encode([
                'iv' => base64_encode($iv),
                'value' => $encrypted,
                'mac' => $this->createMac(base64_encode($iv), $encrypted),
            ]));
        }

        if ($encrypted === false) {
            throw new EncryptException('Could not encrypt the data.');
        }

        return $payload;
    }

    public function decrypt(string $payload, bool $unserialize = true): mixed
    {
        $payload = $this->getJsonPayload($payload);

        $iv = base64_decode($payload['iv']);

        if ($this->isAEAD()) {
            $decrypted = openssl_decrypt(
                $payload['value'],
                $this->cipher,
                $this->key,
                0,
                $iv,
                base64_decode($payload['tag'])
            );
        } else {
            $this->validateMac($payload);
            $decrypted = openssl_decrypt($payload['value'], $this->cipher, $this->key, 0, $iv);
        }

        if ($decrypted === false) {
            throw new DecryptException('Could not decrypt the data.');
        }

        return $unserialize ? unserialize($decrypted) : $decrypted;
    }

    public function encryptString(string $value): string
    {
        return $this->encrypt($value, false);
    }

    public function decryptString(string $payload): string
    {
        return $this->decrypt($payload, false);
    }

    protected function validateKey(string $key, string $cipher): void
    {
        if (!isset(static::$supportedCiphers[$cipher])) {
            throw new \InvalidArgumentException("Unsupported cipher: {$cipher}");
        }

        $expectedSize = static::$supportedCiphers[$cipher]['size'];

        if (strlen($key) !== $expectedSize) {
            throw new \InvalidArgumentException(
                "Key length must be {$expectedSize} bytes for cipher {$cipher}"
            );
        }
    }

    protected function isAEAD(): bool
    {
        return static::$supportedCiphers[$this->cipher]['aead'];
    }

    protected function createMac(string $iv, string $encrypted): string
    {
        return hash_hmac('sha256', base64_encode($iv) . $encrypted, $this->key);
    }

    protected function validateMac(array $payload): void
    {
        $calculated = $this->createMac($payload['iv'], $payload['value']);

        if (!hash_equals($calculated, $payload['mac'])) {
            throw new DecryptException('MAC verification failed.');
        }
    }

    protected function getJsonPayload(string $payload): array
    {
        $decoded = base64_decode($payload);
        if ($decoded === false) {
            throw new DecryptException('Invalid base64 encoding.');
        }

        $payload = json_decode($decoded, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            throw new DecryptException('Invalid JSON payload.');
        }

        if (!$this->validPayload($payload)) {
            throw new DecryptException('Invalid payload format.');
        }

        return $payload;
    }

    protected function validPayload(array $payload): bool
    {
        $required = $this->isAEAD() ? ['iv', 'value', 'tag'] : ['iv', 'value', 'mac'];

        foreach ($required as $key) {
            if (!isset($payload[$key]) || !is_string($payload[$key])) {
                return false;
            }
        }

        return true;
    }

    public function generateKey(string $cipher = 'aes-256-cbc'): string
    {
        if (!isset(static::$supportedCiphers[$cipher])) {
            throw new \InvalidArgumentException("Unsupported cipher: {$cipher}");
        }

        return random_bytes(static::$supportedCiphers[$cipher]['size']);
    }

    public static function generateKeyString(string $cipher = 'aes-256-cbc'): string
    {
        if (!isset(static::$supportedCiphers[$cipher])) {
            throw new \InvalidArgumentException("Unsupported cipher: {$cipher}");
        }

        return base64_encode(random_bytes(static::$supportedCiphers[$cipher]['size']));
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getCipher(): string
    {
        return $this->cipher;
    }
}
