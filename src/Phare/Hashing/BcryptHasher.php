<?php

namespace Phare\Hashing;

class BcryptHasher implements HasherInterface
{
    protected int $rounds = 10;

    public function __construct(array $options = [])
    {
        $this->rounds = $options['rounds'] ?? $this->rounds;
    }

    public function make(string $value, array $options = []): string
    {
        $cost = $options['rounds'] ?? $this->rounds;

        $hash = password_hash($value, PASSWORD_BCRYPT, ['cost' => $cost]);

        if ($hash === false) {
            throw new \RuntimeException('Bcrypt hashing not supported.');
        }

        return $hash;
    }

    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $options['rounds'] ?? $this->rounds,
        ]);
    }

    public function info(string $hashedValue): array
    {
        return password_get_info($hashedValue);
    }

    public function setRounds(int $rounds): void
    {
        $this->rounds = $rounds;
    }
}
