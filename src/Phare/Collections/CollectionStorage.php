<?php

declare(strict_types=1);

namespace Phare\Collections;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Phalcon\Support\Collection as PhalconCollection;
use Traversable;

/**
 * Delegate storage operations without inheriting Phalcon's collection API.
 * Phare's keys(), values(), and replace() return collections, whereas newer
 * Phalcon versions declare incompatible array and void return types.
 *
 * @mixin PhalconCollection
 */
class CollectionStorage implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    protected array $data;

    public function __construct(
        array $data = [],
        private bool $insensitive = true,
    ) {
        $this->data = (new PhalconCollection($data, $insensitive))->toArray();
    }

    private function storage(): PhalconCollection
    {
        return new PhalconCollection($this->data, $this->insensitive);
    }

    public function __call(string $method, array $arguments): mixed
    {
        $storage = $this->storage();

        try {
            return $storage->{$method}(...$arguments);
        } finally {
            $this->data = $storage->toArray();
        }
    }

    public function __get(string $key): mixed
    {
        return $this->storage()->get($key);
    }

    public function __isset(string $key): bool
    {
        return $this->storage()->has($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->__call('set', [$key, $value]);
    }

    public function __unset(string $key): void
    {
        $this->__call('remove', [$key]);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function getIterator(): Traversable
    {
        return $this->storage()->getIterator();
    }

    public function jsonSerialize(): array
    {
        return $this->storage()->jsonSerialize();
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->storage()->offsetExists($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->storage()->offsetGet($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__call('offsetSet', [$offset, $value]);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->__call('offsetUnset', [$offset]);
    }
}
