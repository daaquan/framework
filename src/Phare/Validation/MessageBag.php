<?php

namespace Phare\Validation;

class MessageBag implements \ArrayAccess, \Countable, \JsonSerializable
{
    protected array $messages = [];

    public function __construct(array $messages = [])
    {
        foreach ($messages as $key => $value) {
            $this->messages[$key] = (array)$value;
        }
    }

    public function add(string $key, string $message): self
    {
        if (!isset($this->messages[$key])) {
            $this->messages[$key] = [];
        }

        $this->messages[$key][] = $message;

        return $this;
    }

    public function merge(MessageBag|array $messages): self
    {
        if ($messages instanceof MessageBag) {
            $messages = $messages->messages;
        }

        foreach ($messages as $key => $value) {
            $this->messages[$key] = array_merge(
                $this->messages[$key] ?? [],
                (array)$value
            );
        }

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->messages[$key]) && !empty($this->messages[$key]);
    }

    public function first(?string $key = null): ?string
    {
        if ($key === null) {
            return $this->firstOfAll();
        }

        return $this->messages[$key][0] ?? null;
    }

    public function get(string $key, ?string $format = null): array
    {
        $messages = $this->messages[$key] ?? [];

        if ($format !== null) {
            $messages = array_map(fn ($message) => str_replace(':message', $message, $format), $messages);
        }

        return $messages;
    }

    public function all(?string $format = null): array
    {
        $all = [];

        foreach ($this->messages as $messages) {
            $all = array_merge($all, $messages);
        }

        if ($format !== null) {
            $all = array_map(fn ($message) => str_replace(':message', $message, $format), $all);
        }

        return $all;
    }

    public function keys(): array
    {
        return array_keys($this->messages);
    }

    public function isEmpty(): bool
    {
        return empty($this->messages);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function toArray(): array
    {
        return $this->messages;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return json_encode($this->jsonSerialize());
    }

    protected function firstOfAll(): ?string
    {
        foreach ($this->messages as $messages) {
            if (!empty($messages)) {
                return $messages[0];
            }
        }

        return null;
    }

    // ArrayAccess implementation
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet($offset): array
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->add($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        unset($this->messages[$offset]);
    }
}
