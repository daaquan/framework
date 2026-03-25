<?php

namespace Phare\Auth\Passwords;

use Phalcon\Di\Di;
use PDO;

/**
 * Password reset token manager.
 * Uses the password_reset_tokens table.
 */
class PasswordBroker
{
    private PDO $pdo;
    private int $expireMinutes;

    public function __construct(int $expireMinutes = 60)
    {
        $this->expireMinutes = $expireMinutes;
        $di = Di::getDefault();
        /** @var \Phare\Database\MySql\DatabaseManager $dbManager */
        $dbManager = $di->getShared('dbManager');
        $serviceName = $dbManager->getConnectionService('db');
        $this->pdo = $di->getShared($serviceName)->getInternalHandler();
    }

    /**
     * Create a password reset token for the given user.
     */
    public function createToken(object $user): string
    {
        $email = $user->email;
        $token = bin2hex(random_bytes(32));

        // Delete any existing token for this email
        $stmt = $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE email = :email');
        $stmt->execute([':email' => $email]);

        // Insert new token
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_reset_tokens (email, token, created_at) VALUES (:email, :token, :created_at)'
        );
        $stmt->execute([
            ':email' => $email,
            ':token' => $token,
            ':created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /**
     * Validate a password reset token.
     * Returns true if valid and not expired.
     */
    public function validateToken(string $email, string $token): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT token, created_at FROM password_reset_tokens WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        // Check token matches
        if (!hash_equals($row['token'], $token)) {
            return false;
        }

        // Check expiry
        $createdAt = strtotime($row['created_at']);
        $expiry = $createdAt + ($this->expireMinutes * 60);

        return time() <= $expiry;
    }

    /**
     * Delete the password reset token for the given email.
     */
    public function deleteToken(string $email): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE email = :email');
        $stmt->execute([':email' => $email]);
    }
}
