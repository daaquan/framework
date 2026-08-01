<?php

declare(strict_types=1);

use Phare\Auth\Passkeys\ChallengeStore;
use Phare\Auth\Passkeys\PasskeyCredentialRepository;
use Phare\Auth\Passkeys\PasskeyRegistrar;
use Phare\Auth\Passkeys\PasskeyRegistrationVerifier;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeRegistrar(
    ?PasskeyCredentialRepository $repo = null,
    ?PasskeyRegistrationVerifier $verifier = null,
    ?ChallengeStore $store = null,
    int $ttl = 120
): PasskeyRegistrar {
    $repo ??= new class() implements PasskeyCredentialRepository
    {
        public array $stored = [];

        public function findByCredentialId(string $credentialId, string|int|null $userHandle = null): ?array
        {
            return null;
        }

        public function storeCredential(string|int|null $userHandle, array $credential): void
        {
            $this->stored[] = compact('userHandle', 'credential');
        }
    };

    $verifier ??= new class() implements PasskeyRegistrationVerifier
    {
        public function verify(array $attestation, string $expectedChallenge): ?array
        {
            return ['credential_id' => $attestation['id'] ?? 'cred-x', 'public_key' => 'pk'];
        }
    };

    $store ??= new class() implements ChallengeStore
    {
        private array $data = [];

        public function put(string $key, string $challenge, int $ttlSeconds): void
        {
            $this->data[$key] = $challenge;
        }

        public function get(string $key): ?string
        {
            return $this->data[$key] ?? null;
        }

        public function forget(string $key): void
        {
            unset($this->data[$key]);
        }
    };

    return new PasskeyRegistrar($repo, $verifier, $store, $ttl);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('begin returns a challenge payload with user info', function () {
    $registrar = makeRegistrar();
    $payload = $registrar->begin('user-42', 'alice');

    expect($payload['challenge'])->toBeString()->not->toBeEmpty()
        ->and($payload['expiresIn'])->toBe(120)
        ->and($payload['userHandle'])->toBe('user-42')
        ->and($payload['userName'])->toBe('alice');
});

it('complete verifies attestation, stores credential, and clears challenge', function () {
    $stored = [];
    $forgotten = false;

    $repo = new class($stored) implements PasskeyCredentialRepository
    {
        public function __construct(private array &$stored) {}

        public function findByCredentialId(string $credentialId, string|int|null $userHandle = null): ?array
        {
            return null;
        }

        public function storeCredential(string|int|null $userHandle, array $credential): void
        {
            $this->stored[] = compact('userHandle', 'credential');
        }
    };

    $store = new class($forgotten) implements ChallengeStore
    {
        private array $data = ['passkey:register:user-42' => 'expected-bytes'];

        public function __construct(private bool &$forgotten) {}

        public function put(string $key, string $challenge, int $ttlSeconds): void {}

        public function get(string $key): ?string
        {
            return $this->data[$key] ?? null;
        }

        public function forget(string $key): void
        {
            $this->forgotten = true;
        }
    };

    $verifier = new class() implements PasskeyRegistrationVerifier
    {
        public function verify(array $attestation, string $expectedChallenge): ?array
        {
            if ($attestation['id'] === 'cred-reg-1' && $expectedChallenge === 'expected-bytes') {
                return ['credential_id' => 'cred-reg-1', 'public_key' => 'pubkey-bytes'];
            }

            return null;
        }
    };

    $registrar = new PasskeyRegistrar($repo, $verifier, $store, 120);
    $result = $registrar->complete(['id' => 'cred-reg-1'], 'user-42');

    expect($result)->not->toBeNull()
        ->and($result['credential_id'])->toBe('cred-reg-1')
        ->and($forgotten)->toBeTrue()
        ->and($stored)->toHaveCount(1)
        ->and($stored[0]['userHandle'])->toBe('user-42');
});

it('complete returns null when verifier rejects attestation and does not store', function () {
    $stored = [];

    $repo = new class($stored) implements PasskeyCredentialRepository
    {
        public function __construct(private array &$stored) {}

        public function findByCredentialId(string $credentialId, string|int|null $userHandle = null): ?array
        {
            return null;
        }

        public function storeCredential(string|int|null $userHandle, array $credential): void
        {
            $this->stored[] = $credential;
        }
    };

    $store = new class() implements ChallengeStore
    {
        public function put(string $key, string $challenge, int $ttlSeconds): void {}

        public function get(string $key): ?string
        {
            return 'challenge';
        }

        public function forget(string $key): void {}
    };

    $verifier = new class() implements PasskeyRegistrationVerifier
    {
        public function verify(array $attestation, string $expectedChallenge): ?array
        {
            return null;
        }
    };

    $registrar = new PasskeyRegistrar($repo, $verifier, $store, 120);
    $result = $registrar->complete(['id' => 'bad-cred'], 'user-42');

    expect($result)->toBeNull()
        ->and($stored)->toBeEmpty();
});

it('complete throws when challenge is missing', function () {
    $store = new class() implements ChallengeStore
    {
        public function put(string $key, string $challenge, int $ttlSeconds): void {}

        public function get(string $key): ?string
        {
            return null;
        }

        public function forget(string $key): void {}
    };

    $registrar = makeRegistrar(store: $store);

    expect(fn () => $registrar->complete(['id' => 'cred-x'], 'user-no-challenge'))
        ->toThrow(RuntimeException::class, 'challenge is missing or expired');
});
