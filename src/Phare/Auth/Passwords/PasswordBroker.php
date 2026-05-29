<?php

namespace Phare\Auth\Passwords;

use Phare\Contracts\Auth\CanResetPassword;
use Phare\Hashing\HasherInterface;

/**
 * Password reset token manager.
 *
 * Tokens are returned to the caller in plaintext (for the reset link) but
 * only ever persisted as a hash, so a database compromise does not yield
 * usable reset tokens.
 */
class PasswordBroker
{
    public function __construct(
        private TokenRepositoryInterface $tokens,
        private HasherInterface $hasher,
        private int $expireMinutes = 60,
    ) {}

    /**
     * Create a password reset token for the given user.
     *
     * Returns the plaintext token; storage receives only the hash.
     */
    public function createToken(CanResetPassword $user): string
    {
        $email = $user->getEmailForPasswordReset();
        $this->tokens->delete($email);

        $token = bin2hex(random_bytes(32));
        $this->tokens->create($email, $this->hasher->make($token));

        return $token;
    }

    /**
     * Validate a password reset token. Returns true if valid and not expired.
     */
    public function validateToken(string $email, #[\SensitiveParameter] string $token): bool
    {
        $row = $this->tokens->find($email);

        if (!$row || !$this->hasher->check($token, $row['token'])) {
            return false;
        }

        return !$this->tokenExpired($row['created_at']);
    }

    /**
     * Delete the password reset token for the given email.
     */
    public function deleteToken(string $email): void
    {
        $this->tokens->delete($email);
    }

    private function tokenExpired(string $createdAt): bool
    {
        $expiresAt = strtotime($createdAt) + ($this->expireMinutes * 60);

        return time() > $expiresAt;
    }
}
