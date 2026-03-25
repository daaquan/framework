<?php

namespace Phare\Support;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

abstract class DataTransferObject
{
    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    public function fill(array $data): static
    {
        $reflection = new ReflectionClass($this);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if (array_key_exists($name, $data)) {
                $this->{$name} = $this->castValue($property, $data[$name]);
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $result = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isInitialized($this)) {
                $value = $property->getValue($this);
                $result[$property->getName()] = $value instanceof self ? $value->toArray() : $value;
            }
        }

        return $result;
    }

    public function only(string ...$keys): array
    {
        return array_intersect_key($this->toArray(), array_flip($keys));
    }

    public function except(string ...$keys): array
    {
        return array_diff_key($this->toArray(), array_flip($keys));
    }

    protected function castValue(ReflectionProperty $property, mixed $value): mixed
    {
        $type = $property->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $className = $type->getName();
            if (enum_exists($className)) {
                return $className::from($value);
            }
            if (is_subclass_of($className, self::class) && is_array($value)) {
                return new $className($value);
            }
        }

        return $value;
    }

    public static function from(array $data): static
    {
        return new static($data);
    }
}
