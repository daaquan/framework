<?php

declare(strict_types=1);

namespace Phare\Providers;

use Phare\Auth\Passkeys\ChallengeStore;
use Phare\Auth\Passkeys\InMemoryChallengeStore;
use Phare\Auth\Passkeys\PasskeyAssertionVerifier;
use Phare\Auth\Passkeys\PasskeyAuthenticator;
use Phare\Auth\Passkeys\PasskeyCredentialRepository;
use Phare\Auth\Passkeys\PasskeyRegistrar;
use Phare\Auth\Passkeys\PasskeyRegistrationVerifier;
use Phare\Foundation\AbstractApplication as Application;
use Phare\Support\ServiceProvider;

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
class PasskeyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /** @var Application $app */
        $app = $this->app;
        // Default challenge store: in-memory (replace with cache-backed in production).
        if (!$app->has(ChallengeStore::class)) {
            $app->singleton(ChallengeStore::class, InMemoryChallengeStore::class);
        }

        $ttl = (int)($app->has('config') ? ($app['config']['passkeys']['challenge_ttl'] ?? 300) : 300);

        $app->singleton(PasskeyAuthenticator::class, function () use ($app, $ttl) {
            return new PasskeyAuthenticator(
                $app[PasskeyCredentialRepository::class],
                $app[PasskeyAssertionVerifier::class],
                $app[ChallengeStore::class],
                $ttl
            );
        });

        $app->singleton(PasskeyRegistrar::class, function () use ($app, $ttl) {
            return new PasskeyRegistrar(
                $app[PasskeyCredentialRepository::class],
                $app[PasskeyRegistrationVerifier::class],
                $app[ChallengeStore::class],
                $ttl
            );
        });

        // Convenience aliases.
        $app->alias('passkey.authenticator', PasskeyAuthenticator::class);
        $app->alias('passkey.registrar', PasskeyRegistrar::class);
    }
}
