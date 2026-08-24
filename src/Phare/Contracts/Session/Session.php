<?php

namespace Phare\Contracts\Session;

/**
 * Phare's session contract.
 *
 * This used to extend `Phalcon\Session\ManagerInterface`, which meant every
 * consumer type-hinting a Phare session also depended on Phalcon. The surface
 * is now declared here. `Phare\Session\SessionManager` additionally implements
 * the Phalcon interface so Phalcon components that resolve the DI `session`
 * service keep working, but Phare code should depend only on this contract.
 */
interface Session
{
    // -- Phare additions --------------------------------------------------

    /** Read a value and remove it in the same call. */
    public function pull(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value): void;

    /** Append a value to an array held under the given key. */
    public function add(string $key, mixed $value): void;

    public function forget(string $key): void;

    /** Remove everything and restart the session. */
    public function clear(): void;

    /** @param  array<string, mixed>  $attributes */
    public function replace(array $attributes): void;

    // -- Manager surface --------------------------------------------------

    public function get(string $key, mixed $defaultValue = null, bool $remove = false): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function remove(string $key): void;

    public function exists(): bool;

    public function start(): bool;

    public function status(): int;

    public function destroy(): void;

    public function getId(): string;

    public function setId(string $sessionId): static;

    public function getName(): string;

    public function setName(string $name): static;

    /** @return array<string, mixed> */
    public function getOptions(): array;

    /** @param  array<string, mixed>  $options */
    public function setOptions(array $options): void;

    public function getAdapter(): \SessionHandlerInterface;

    public function setAdapter(\SessionHandlerInterface $adapter): static;

    public function regenerateId(bool $deleteOldSession = true): static;
}
