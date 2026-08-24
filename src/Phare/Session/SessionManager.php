<?php

namespace Phare\Session;

use Phalcon\Session\Manager;
use Phalcon\Session\ManagerInterface;
use Phare\Contracts\Session\Session;

/**
 * Phare's session manager.
 *
 * It wraps a Phalcon session manager by composition rather than extending it,
 * so Phalcon's manager implementation is not part of Phare's public API.
 *
 * It still implements `Phalcon\Session\ManagerInterface` on purpose: Phalcon
 * components resolve the DI service named `session` and type-check it against
 * that interface (`Phalcon\Flash\Session` is the one in use here). Satisfying
 * the contract is cheap; inheriting the implementation was not.
 */
class SessionManager implements ManagerInterface, Session
{
    protected Manager $manager;

    /** @param  array<string, mixed>  $options */
    public function __construct(array $options = [])
    {
        $this->manager = new Manager($options);
    }

    /** Escape hatch for the Phalcon-native code paths that need the real object. */
    public function getPhalconManager(): Manager
    {
        return $this->manager;
    }

    // -- Phare additions -------------------------------------------------

    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default, true);
    }

    public function put(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /** Append a value to an array held under the given key. */
    public function add(string $key, mixed $value): void
    {
        $array = $this->get($key, []);
        $array[] = $value;
        $this->set($key, $array);
    }

    /** Remove all items from the session and restart it. */
    public function clear(): void
    {
        $this->destroy();
        $this->start();
    }

    /** @param  array<string, mixed>  $attributes */
    public function replace(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function forget(string $key): void
    {
        $this->remove($key);
    }

    // -- Delegated manager surface ---------------------------------------

    public function __get(string $key): mixed
    {
        return $this->manager->get($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->manager->set($key, $value);
    }

    public function __isset(string $key): bool
    {
        return $this->manager->has($key);
    }

    public function __unset(string $key): void
    {
        $this->manager->remove($key);
    }

    public function get(string $key, mixed $defaultValue = null, bool $remove = false): mixed
    {
        return $this->manager->get($key, $defaultValue, $remove);
    }

    public function set(string $key, mixed $value): void
    {
        $this->manager->set($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->manager->has($key);
    }

    public function remove(string $key): void
    {
        $this->manager->remove($key);
    }

    public function exists(): bool
    {
        return $this->manager->exists();
    }

    public function start(): bool
    {
        return $this->manager->start();
    }

    public function status(): int
    {
        return $this->manager->status();
    }

    public function destroy(): void
    {
        $this->manager->destroy();
    }

    public function getId(): string
    {
        return $this->manager->getId();
    }

    public function setId(string $sessionId): static
    {
        $this->manager->setId($sessionId);

        return $this;
    }

    public function getName(): string
    {
        return $this->manager->getName();
    }

    public function setName(string $name): static
    {
        $this->manager->setName($name);

        return $this;
    }

    /** @return array<string, mixed> */
    public function getOptions(): array
    {
        return $this->manager->getOptions();
    }

    /** @param  array<string, mixed>  $options */
    public function setOptions(array $options): void
    {
        $this->manager->setOptions($options);
    }

    public function getAdapter(): \SessionHandlerInterface
    {
        return $this->manager->getAdapter();
    }

    public function setAdapter(\SessionHandlerInterface $adapter): static
    {
        $this->manager->setAdapter($adapter);

        return $this;
    }

    public function regenerateId(bool $deleteOldSession = true): static
    {
        $this->manager->regenerateId($deleteOldSession);

        return $this;
    }
}
