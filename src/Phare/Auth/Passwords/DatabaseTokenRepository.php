<?php

namespace Phare\Auth\Passwords;

use PDO;

/**
 * PDO-backed token store using the password_reset_tokens table.
 *
 * Stores only the hashed token supplied by the broker.
 */
class DatabaseTokenRepository implements TokenRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
        private string $table = 'password_reset_tokens',
    ) {}

    public function create(string $email, string $hashedToken): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table} (email, token, created_at) VALUES (:email, :token, :created_at)"
        );
        $stmt->execute([
            ':email' => $email,
            ':token' => $hashedToken,
            ':created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function find(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT token, created_at FROM {$this->table} WHERE email = :email LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function delete(string $email): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE email = :email");
        $stmt->execute([':email' => $email]);
    }
}
