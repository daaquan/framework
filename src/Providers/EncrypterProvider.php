<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
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
        $app->singleton('random', \Phalcon\Encryption\Security\Random::class);

        $app->singleton('security', function () use ($app) {
            $security = new \Phalcon\Encryption\Security();
            $security->setWorkFactor(12);
            $security->setDI($app);

            return $security;
        });

        $app->singleton('encrypter', function () use ($app) {
            $config = $app['config'];
            $appKey = $config->path('app.key');
            [$method, $encoded] = explode(':', $appKey);

            // Dynamic function name for decoding
            $decodeMethod = "{$method}_decode";
            if (!function_exists($decodeMethod)) {
                throw new \RuntimeException("Key decode method `{$decodeMethod}` does not exist.");
            }

            // Decode the key using the dynamic decode method
            $key = $decodeMethod(substr($encoded, strlen($method) + 1));

            // Instantiate Crypt with the key and set the cipher method
            return (new \Phalcon\Encryption\Crypt())
                ->setKey($key)
                ->setCipher($config->path('app.cipher'));
        });
    }
}
