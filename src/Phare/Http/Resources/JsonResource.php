<?php

namespace Phare\Http\Resources;

use Phare\Contracts\Support\Arrayable;
use Phare\Contracts\Support\Jsonable;

abstract class JsonResource implements \JsonSerializable, Arrayable, Jsonable
{
    protected mixed $resource;

    protected ?array $with = null;

    protected ?array $additional = null;

    public static ?string $wrap = 'data';

    public function __construct(mixed $resource)
    {
        $this->resource = $resource;
    }

    public static function make(mixed ...$parameters): static
    {
        return new static(...$parameters);
    }

    public static function collection(iterable $resource): ResourceCollection
    {
        return new ResourceCollection($resource, static::class);
    }

    abstract public function toArray(): array;

    public function with(): array
    {
        return $this->with ?? [];
    }

    public function additional(array $data): static
    {
        $this->additional = $data;

        return $this;
    }

    public function response(): JsonResourceResponse
    {
        return new JsonResourceResponse($this);
    }

    public function toResponse(): JsonResourceResponse
    {
        return $this->response();
    }

    public function jsonSerialize(): array
    {
        $data = $this->resolve();

        if (is_array($data)) {
            $data = $data;
        } elseif ($data instanceof Arrayable) {
            $data = $data->toArray();
        } elseif ($data instanceof \JsonSerializable) {
            $data = $data->jsonSerialize();
        }

        return is_array($data) ? $data : [$data];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    protected function resolve(): array
    {
        $data = $this->toArray();

        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        }

        return $this->filter((array)$data);
    }

    protected function filter(array $data): array
    {
        $index = -1;

        foreach ($data as $key => $value) {
            $index++;

            if (is_array($value) || is_null($value)) {
                $data[$key] = $value;
            } elseif (is_int($key) && $value instanceof MissingValue) {
                unset($data[$key]);
            } elseif ($value instanceof MissingValue) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    public function when(bool $condition, mixed $value, mixed $default = null): mixed
    {
        if ($condition) {
            return $value instanceof \Closure ? $value() : $value;
        }

        return $default instanceof \Closure ? $default() : $default;
    }

    public function whenHas(string $attribute, mixed $value = null, mixed $default = null): mixed
    {
        if ($this->offsetExists($attribute)) {
            return $value instanceof \Closure ? $value($this->resource->{$attribute}) : ($value ?? $this->resource->{$attribute});
        }

        return $default instanceof \Closure ? $default() : $default;
    }

    public function whenNotNull(mixed $value, mixed $default = null): mixed
    {
        return !is_null($value) ? $value : $default;
    }

    public function mergeWhen(bool $condition, array $data): array
    {
        return $condition ? $data : [];
    }

    public function merge(array $data): MergeValue
    {
        return new MergeValue($data);
    }

    protected function whenLoaded(string $relationship, mixed $value = null, mixed $default = null): mixed
    {
        if (func_num_args() === 0 || $this->relationLoaded($relationship)) {
            return $value instanceof \Closure ? $value() : ($value ?? $this->resource->{$relationship});
        }

        return $default instanceof \Closure ? $default() : $default;
    }

    protected function relationLoaded(string $relationship): bool
    {
        if (!is_object($this->resource)) {
            return false;
        }

        if (method_exists($this->resource, 'relationLoaded')) {
            return $this->resource->relationLoaded($relationship);
        }

        return property_exists($this->resource, $relationship) &&
               !is_null($this->resource->{$relationship});
    }

    public function offsetExists($offset): bool
    {
        if (is_array($this->resource)) {
            return array_key_exists($offset, $this->resource);
        }

        if (is_object($this->resource)) {
            return property_exists($this->resource, $offset) ||
                   (method_exists($this->resource, '__isset') && $this->resource->__isset($offset));
        }

        return false;
    }

    public function offsetGet($offset): mixed
    {
        if (is_array($this->resource)) {
            return $this->resource[$offset];
        }

        if (is_object($this->resource)) {
            return $this->resource->{$offset};
        }

        return null;
    }

    public function __isset(string $key): bool
    {
        return $this->offsetExists($key);
    }

    public function __get(string $key): mixed
    {
        return $this->offsetGet($key);
    }

    public static function withoutWrapping(): void
    {
        static::$wrap = null;
    }

    public static function wrap(string $value): void
    {
        static::$wrap = $value;
    }
}
