<?php

namespace Phare\Pagination;

use Phare\Collections\Collection;
use Phare\Contracts\Support\Arrayable;
use UnexpectedValueException;

class Cursor implements Arrayable
{
    protected array $parameters;

    protected bool $pointsToNextItems;

    public function __construct(array $parameters, bool $pointsToNextItems = true)
    {
        $this->parameters = $parameters;
        $this->pointsToNextItems = $pointsToNextItems;
    }

    public function parameter(string $parameterName): mixed
    {
        if (!array_key_exists($parameterName, $this->parameters)) {
            throw new UnexpectedValueException("Unable to find parameter [{$parameterName}] in pagination item.");
        }

        return $this->parameters[$parameterName];
    }

    public function parameters(array $parameterNames): array
    {
        return (new Collection($parameterNames))
            ->map(fn ($parameterName) => $this->parameter($parameterName))
            ->toArray();
    }

    public function pointsToNextItems(): bool
    {
        return $this->pointsToNextItems;
    }

    public function pointsToPreviousItems(): bool
    {
        return !$this->pointsToNextItems;
    }

    public function toArray(): array
    {
        return array_merge($this->parameters, [
            '_pointsToNextItems' => $this->pointsToNextItems,
        ]);
    }

    public function encode(): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($this->toArray())));
    }

    public static function fromEncoded(mixed $encodedString): ?static
    {
        if (!is_string($encodedString)) {
            return null;
        }

        $parameters = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $encodedString)), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parameters)) {
            return null;
        }

        $pointsToNextItems = (bool)($parameters['_pointsToNextItems'] ?? true);
        unset($parameters['_pointsToNextItems']);

        return new static($parameters, $pointsToNextItems);
    }
}
