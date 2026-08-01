<?php

declare(strict_types=1);

use Phare\Auth\Passkeys\ChallengeStore;
use Phare\Auth\Passkeys\PasskeyAssertionVerifier;
use Phare\Auth\Passkeys\PasskeyAuthenticator;
use Phare\Auth\Passkeys\PasskeyCredentialRepository;

it('begins an assertion and returns a challenge payload', function () {
    $authenticator = new PasskeyAuthenticator(
        new class() implements PasskeyCredentialRepository
        {
            public function findByCredentialId(string $credentialId, string|int|null $userHandle = null): ?array
            {
                return ['id' => $credentialId];
            }

            public function storeCredential(string|int|null $userHandle, array $credential): void {}
        },
        new class() implements PasskeyAssertionVerifier
        {
            public function verify(array $assertion, array $credential, string $expectedChallenge): bool
            {
                return true;
            }
        },
        new class() implements ChallengeStore
        {
            public function put(string $key, string $challenge, int $ttlSeconds): void {}

            public function get(string $key): ?string
            {
                return null;
            }

            public function forget(string $key): void {}
        },
        180
    );

    $payload = $authenticator->begin('user-1');

    expect($payload['challenge'])->toBeString()->not->toBeEmpty()
        ->and($payload['expiresIn'])->toBe(180)
        ->and($payload['userHandle'])->toBe('user-1');
});

it('verifies a passkey assertion and clears challenge on success', function () {
    $forgotten = false;
    $store = new class($forgotten) implements ChallengeStore
    {
        public function __construct(private bool &$forgotten) {}

        public function put(string $key, string $challenge, int $ttlSeconds): void {}

        public function get(string $key): ?string
        {
            return 'challenge-bytes';
        }

        public function forget(string $key): void
        {
            $this->forgotten = true;
        }
    };

    $authenticator = new PasskeyAuthenticator(
        new class() implements PasskeyCredentialRepository
        {
            public function findByCredentialId(string $credentialId, string|int|null $userHandle = null): ?array
            {
                return ['credential_id' => $credentialId, 'user' => $userHandle];
            }

            public function storeCredential(string|int|null $userHandle, array $credential): void {}
        },
        new class() implements PasskeyAssertionVerifier
        {
            public function verify(array $assertion, array $credential, string $expectedChallenge): bool
            {
                return $assertion['rawId'] === 'cred-123'
                    && $credential['credential_id'] === 'cred-123'
                    && $expectedChallenge === 'challenge-bytes';
            }
        },
        $store
    );

    $result = $authenticator->verify(['rawId' => 'cred-123'], 'user-1');

    expect($result)->toBeTrue()
        ->and($forgotten)->toBeTrue();
});

it('returns false when credential cannot be found', function () {
    $store = new class() implements ChallengeStore
    {
        public function put(string $key, string $challenge, int $ttlSeconds): void {}

        public function get(string $key): ?string
        {
            return 'challenge-bytes';
        }

        public function forget(string $key): void {}
    };

    $authenticator = new PasskeyAuthenticator(
        new class() implements PasskeyCredentialRepository
        {
            public function findByCredentialId(string $credentialId, string|int|null $userHandle = null): ?array
            {
                return null;
            }

            public function storeCredential(string|int|null $userHandle, array $credential): void {}
        },
        new class() implements PasskeyAssertionVerifier
        {
            public function verify(array $assertion, array $credential, string $expectedChallenge): bool
            {
                return true;
            }
        },
        $store
    );

    expect($authenticator->verify(['rawId' => 'missing']))->toBeFalse();
});
