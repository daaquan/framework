<?php

namespace Phare\Encryption;

class Encrypter
{
    protected string $key;

    protected string $cipher;

    /**
     * Retired keys retained for decryption during rotation. Encryption always
     * uses the current key; decryption falls back to these on verification
     * failure so ciphertext produced under an old key keeps decrypting.
     *
     * @var list<string>
     */
    protected array $previousKeys = [];

    protected static array $supportedCiphers = [
        'aes-128-cbc' => ['size' => 16, 'aead' => false],
        'aes-256-cbc' => ['size' => 32, 'aead' => false],
        'aes-128-gcm' => ['size' => 16, 'aead' => true],
        'aes-256-gcm' => ['size' => 32, 'aead' => true],
    ];

    /**
     * @param list<string> $previousKeys Retired raw keys to try on decrypt only.
     */
    public function __construct(
        #[\SensitiveParameter] string $key,
        string $cipher = 'aes-256-cbc',
        #[\SensitiveParameter] array $previousKeys = []
    ) {
        $this->validateKey($key, $cipher);

        foreach ($previousKeys as $previousKey) {
            $this->validateKey((string)$previousKey, $cipher);
        }

        $this->key = $key;
        $this->cipher = $cipher;
        $this->previousKeys = array_values(array_map('strval', $previousKeys));
    }

    /**
     * All keys (current first) eligible to attempt decryption.
     *
     * @return list<string>
     */
    protected function allKeys(): array
    {
        return [$this->key, ...$this->previousKeys];
    }

    public function encrypt(#[\SensitiveParameter] mixed $value, bool $serialize = true): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));

        $value = $serialize ? serialize($value) : (string)$value;

        if ($this->isAEAD()) {
            $tag = null;
            $encrypted = openssl_encrypt($value, $this->cipher, $this->key, 0, $iv, $tag);

            // Fail before deriving anything from a false result.
            if ($encrypted === false) {
                throw new EncryptException('Could not encrypt the data.');
            }

            $payload = base64_encode(json_encode([
                'iv' => base64_encode($iv),
                'value' => $encrypted,
                'tag' => base64_encode($tag),
                'mac' => '',
            ]));
        } else {
            $encrypted = openssl_encrypt($value, $this->cipher, $this->key, 0, $iv);

            // Check the result BEFORE computing the MAC: on failure $encrypted is
            // false, which would coerce to '' and produce a MAC over empty data.
            if ($encrypted === false) {
                throw new EncryptException('Could not encrypt the data.');
            }

            $payload = base64_encode(json_encode([
                'iv' => base64_encode($iv),
                'value' => $encrypted,
                'mac' => $this->createMac(base64_encode($iv), $encrypted),
            ]));
        }

        return $payload;
    }

    public function decrypt(string $payload, bool $unserialize = true): mixed
    {
        $payload = $this->getJsonPayload($payload);

        $iv = base64_decode($payload['iv']);

        if ($this->isAEAD()) {
            $tag = base64_decode($payload['tag'], true);

            // AES-GCM mandates a 128-bit (16-byte) authentication tag. A short or
            // attacker-truncated tag dramatically weakens forgery resistance, so
            // reject anything that is not exactly 16 bytes before we ever hand it
            // to openssl_decrypt.
            if ($tag === false || strlen($tag) !== 16) {
                throw new DecryptException('Invalid authentication tag.');
            }

            $decrypted = $this->attemptDecrypt(fn (string $key) => openssl_decrypt(
                $payload['value'],
                $this->cipher,
                $key,
                0,
                $iv,
                $tag
            ));
        } else {
            // Try the MAC against the current key, then each retired key. Only if
            // a key authenticates the payload do we decrypt with that same key.
            $decrypted = $this->attemptDecrypt(function (string $key) use ($payload, $iv) {
                if (!$this->macIsValid($payload, $key)) {
                    return false;
                }

                return openssl_decrypt($payload['value'], $this->cipher, $key, 0, $iv);
            });
        }

        if ($decrypted === false) {
            throw new DecryptException('Could not decrypt the data.');
        }

        return $unserialize ? unserialize($decrypted) : $decrypted;
    }

    /**
     * Attempt a decrypt closure against the current key and, on failure, each
     * retired key in turn (key rotation). Returns the first successful plaintext
     * or false when every key fails.
     */
    protected function attemptDecrypt(\Closure $decryptWith): string|false
    {
        foreach ($this->allKeys() as $key) {
            $result = $decryptWith($key);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    public function encryptString(#[\SensitiveParameter] string $value): string
    {
        return $this->encrypt($value, false);
    }

    public function decryptString(string $payload): string
    {
        return $this->decrypt($payload, false);
    }

    protected function validateKey(#[\SensitiveParameter] string $key, string $cipher): void
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

    protected function createMac(string $iv, string $encrypted, ?string $key = null): string
    {
        return hash_hmac('sha256', base64_encode($iv) . $encrypted, $key ?? $this->key);
    }

    /**
     * Timing-safe MAC check for a specific key (used during key rotation).
     */
    protected function macIsValid(array $payload, string $key): bool
    {
        $calculated = $this->createMac($payload['iv'], $payload['value'], $key);

        return hash_equals($calculated, (string)$payload['mac']);
    }

    protected function validateMac(array $payload): void
    {
        if (!$this->macIsValid($payload, $this->key)) {
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
