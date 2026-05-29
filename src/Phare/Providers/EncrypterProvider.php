<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Encryption\Security;
use Phalcon\Encryption\Security\Random;
use Phare\Encryption\Encrypter;
use Phare\Foundation\AbstractApplication as Application;

/**
 * Service provider for security and encryption.
 */
class EncrypterProvider implements ServiceProviderInterface
{
    /**
     * @throws \RuntimeException If the key decode method does not exist.
     */
    public function register(Application|DiInterface $app): void
    {
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

            return new Encrypter($key, $cipher);
        });
    }
}
