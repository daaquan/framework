<?php

namespace Phare\Providers;

use Phalcon\Config\ConfigInterface;
use Phalcon\Encryption\Security;
use Phalcon\Encryption\Security\Random;
use Phare\Encryption\Encrypter;
use Phare\Support\ServiceProvider;

/**
 * Service provider for security and encryption.
 */
class EncrypterProvider extends ServiceProvider
{
    /**
     * @throws \RuntimeException If the key decode method does not exist.
     */
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('random', Random::class);

        $app->singleton('security', function () use ($app) {
            $security = new Security();
            $security->setWorkFactor(12);
            $security->setDI($app);

            return $security;
        });

        $app->singleton('encrypter', function () use ($app) {
            $config = $app['config'];

            $key = (string)$config->path('app.key');
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7), true) ?: '';
            }

            // Phare\Encryption\Encrypter only registers lower-case cipher names.
            $cipher = strtolower((string)$config->path('app.cipher', 'aes-256-cbc'));

            // Retired keys retained for decryption during key rotation. Accepts a
            // list of raw or "base64:"-prefixed keys; encryption always uses the
            // current key, but ciphertext under any previous key still decrypts.
            $previousKeys = $config->path('app.previous_keys', []);
            if ($previousKeys instanceof ConfigInterface) {
                $previousKeys = $previousKeys->toArray();
            }
            $previousKeys = array_map(static function ($previousKey): string {
                $previousKey = (string)$previousKey;
                if (str_starts_with($previousKey, 'base64:')) {
                    return base64_decode(substr($previousKey, 7), true) ?: '';
                }

                return $previousKey;
            }, (array)$previousKeys);

            return new Encrypter($key, $cipher, array_values($previousKeys));
        });
    }
}
