<?php

namespace Phare\Collections;

class Stringable implements \Stringable
{
    public function __construct(protected string $value) {}

    public function __toString(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function upper(): static
    {
        return new static(Str::upper($this->value));
    }

    public function lower(): static
    {
        return new static(Str::lower($this->value));
    }

    public function trim(string $characters = " \t\n\r\0\x0B"): static
    {
        return new static(trim($this->value, $characters));
    }

    public function ltrim(string $characters = " \t\n\r\0\x0B"): static
    {
        return new static(ltrim($this->value, $characters));
    }

    public function rtrim(string $characters = " \t\n\r\0\x0B"): static
    {
        return new static(rtrim($this->value, $characters));
    }

    public function append(string ...$strings): static
    {
        return new static($this->value . implode('', $strings));
    }

    public function prepend(string ...$strings): static
    {
        return new static(implode('', $strings) . $this->value);
    }

    public function replace(string|array $search, string|array $replace, bool $caseSensitive = true): static
    {
        return new static(Str::replace($search, $replace, $this->value, $caseSensitive));
    }

    public function replaceFirst(string $search, string $replace): static
    {
        return new static(Str::replaceFirst($search, $replace, $this->value));
    }

    public function replaceLast(string $search, string $replace): static
    {
        return new static(Str::replaceLast($search, $replace, $this->value));
    }

    public function remove(string|array $search, bool $caseSensitive = true): static
    {
        return new static(Str::remove($search, $this->value, $caseSensitive));
    }

    public function length(?string $encoding = null): int
    {
        return Str::length($this->value, $encoding);
    }

    public function limit(int $limit = 100, string $end = '...'): static
    {
        return new static(Str::limit($this->value, $limit, $end));
    }

    public function contains(string|array $needles, bool $ignoreCase = false): bool
    {
        return Str::contains($this->value, $needles, $ignoreCase);
    }

    public function containsAll(array $needles, bool $ignoreCase = false): bool
    {
        return Str::containsAll($this->value, $needles, $ignoreCase);
    }

    public function startsWith(string|array $needles): bool
    {
        return Str::startsWith($this->value, $needles);
    }

    public function endsWith(string|array $needles): bool
    {
        return Str::endsWith($this->value, $needles);
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    public function isNotEmpty(): bool
    {
        return $this->value !== '';
    }

    public function isBlank(): bool
    {
        return Str::isBlank($this->value);
    }

    public function isFilled(): bool
    {
        return Str::isFilled($this->value);
    }

    public function slug(): static
    {
        return new static(Str::slug($this->value));
    }

    public function snake(string $delimiter = '_'): static
    {
        return new static(Str::snake($this->value, $delimiter));
    }

    public function camel(): static
    {
        return new static(Str::camel($this->value));
    }

    public function studly(): static
    {
        return new static(Str::studly($this->value));
    }

    public function kebab(): static
    {
        return new static(Str::kebab($this->value));
    }

    public function title(): static
    {
        return new static(Str::title($this->value));
    }

    public function ucfirst(): static
    {
        return new static(Str::ucfirst($this->value));
    }

    public function lcfirst(): static
    {
        return new static(Str::lcfirst($this->value));
    }

    public function reverse(): static
    {
        return new static(Str::reverse($this->value));
    }

    public function repeat(int $times): static
    {
        return new static(Str::repeat($this->value, $times));
    }

    public function after(string $search): static
    {
        return new static(Str::after($this->value, $search));
    }

    public function afterLast(string $search): static
    {
        return new static(Str::afterLast($this->value, $search));
    }

    public function before(string $search): static
    {
        return new static(Str::before($this->value, $search));
    }

    public function beforeLast(string $search): static
    {
        return new static(Str::beforeLast($this->value, $search));
    }

    public function between(string $from, string $to): static
    {
        return new static(Str::between($this->value, $from, $to));
    }

    public function finish(string $cap): static
    {
        return new static(Str::finish($this->value, $cap));
    }

    public function start(string $prefix): static
    {
        return new static(Str::start($this->value, $prefix));
    }

    public function padBoth(int $length, string $pad = ' '): static
    {
        return new static(Str::padBoth($this->value, $length, $pad));
    }

    public function padLeft(int $length, string $pad = ' '): static
    {
        return new static(Str::padLeft($this->value, $length, $pad));
    }

    public function padRight(int $length, string $pad = ' '): static
    {
        return new static(Str::padRight($this->value, $length, $pad));
    }

    public function mask(string $character, int $index, ?int $length = null, string $encoding = 'UTF-8'): static
    {
        return new static(Str::mask($this->value, $character, $index, $length, $encoding));
    }

    public function squish(): static
    {
        return new static(Str::squish($this->value));
    }

    public function wrap(string $before, ?string $after = null): static
    {
        return new static(Str::wrap($this->value, $before, $after));
    }

    public function take(int $limit): static
    {
        return new static(Str::take($this->value, $limit));
    }

    public function when(bool $condition, callable $callback, ?callable $default = null): static
    {
        if ($condition) {
            $result = $callback($this);

            return $result instanceof static ? $result : $this;
        }

        if ($default !== null) {
            $result = $default($this);

            return $result instanceof static ? $result : $this;
        }

        return $this;
    }

    public function unless(bool $condition, callable $callback, ?callable $default = null): static
    {
        return $this->when(!$condition, $callback, $default);
    }

    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    public function tap(callable $callback): static
    {
        $callback($this);

        return $this;
    }

    public function wordCount(?string $characters = null): int
    {
        return Str::wordCount($this->value, $characters);
    }
}
