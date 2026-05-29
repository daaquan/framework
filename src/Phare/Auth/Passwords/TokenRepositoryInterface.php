<?php

namespace Phare\Auth\Passwords;

/**
 * Persistence boundary for password reset tokens.
 *
 * Implementations store the already-hashed token; the broker owns hashing
 * and verification so no plaintext token ever reaches storage.
 */
interface TokenRepositoryInterface
{
    /**
     * Store a hashed reset token for the given email.
     */
    public function create(string $email, string $hashedToken): void;

    /**
     * Find the stored token record for the email, or null if none exists.
     *
     * @return array{token: string, created_at: string}|null
     */
    public function find(string $email): ?array;

    /**
     * Delete any stored token for the email.
     */
    public function delete(string $email): void;
}
