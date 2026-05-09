<?php

declare(strict_types=1);

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Auth\Passkeys\ChallengeStore;
use Phare\Auth\Passkeys\InMemoryChallengeStore;
use Phare\Auth\Passkeys\PasskeyAuthenticator;
use Phare\Auth\Passkeys\PasskeyRegistrar;
use Phare\Foundation\AbstractApplication as Application;

/**
 * Register passkey (WebAuthn) authentication services.
 *
 * Applications should bind their own implementations of:
 *   - PasskeyCredentialRepository  (database-backed credential store)
 *   - PasskeyAssertionVerifier     (CBOR/COSE verifier, e.g. web-auth/webauthn-lib)
 *   - PasskeyRegistrationVerifier  (attestation verifier)
 *   - ChallengeStore               (cache-backed, default: InMemoryChallengeStore)
 *
 * before or after registering this provider.  The services below fall back to
 * sensible no-op / in-memory defaults so the container always resolves.
 */
class PasskeyServiceProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        // Default challenge store: in-memory (replace with cache-backed in production).
        if (! $app->has(ChallengeStore::class)) {
            $app->singleton(ChallengeStore::class, InMemoryChallengeStore::class);
        }

        $ttl = (int) ($app->has('config') ? ($app['config']['passkeys']['challenge_ttl'] ?? 300) : 300);

        $app->singleton(PasskeyAuthenticator::class, function () use ($app, $ttl) {
            return new PasskeyAuthenticator(
                $app[\Phare\Auth\Passkeys\PasskeyCredentialRepository::class],
                $app[\Phare\Auth\Passkeys\PasskeyAssertionVerifier::class],
                $app[ChallengeStore::class],
                $ttl
            );
        });

        $app->singleton(PasskeyRegistrar::class, function () use ($app, $ttl) {
            return new PasskeyRegistrar(
                $app[\Phare\Auth\Passkeys\PasskeyCredentialRepository::class],
                $app[\Phare\Auth\Passkeys\PasskeyRegistrationVerifier::class],
                $app[ChallengeStore::class],
                $ttl
            );
        });

        // Convenience aliases.
        $app->alias('passkey.authenticator', PasskeyAuthenticator::class);
        $app->alias('passkey.registrar', PasskeyRegistrar::class);
    }
}
