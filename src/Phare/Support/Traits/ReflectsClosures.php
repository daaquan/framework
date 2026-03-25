<?php

namespace Phare\Support\Traits;

trait ReflectsClosures
{
    /**
     * Resolve event class names from the first typed Closure parameter.
     *
     * @return list<string>
     */
    protected function firstClosureParameterTypes(\Closure $closure): array
    {
        $reflection = new \ReflectionFunction($closure);
        $parameters = $reflection->getParameters();

        if ($parameters === []) {
            throw new \InvalidArgumentException('Unable to infer event type from listener Closure.');
        }

        $type = $parameters[0]->getType();

        if ($type instanceof \ReflectionNamedType) {
            return $this->resolveNamedType($type);
        }

        if ($type instanceof \ReflectionUnionType) {
            $types = [];

            foreach ($type->getTypes() as $namedType) {
                $types = array_merge($types, $this->resolveNamedType($namedType));
            }

            if ($types !== []) {
                return array_values(array_unique($types));
            }
        }

        throw new \InvalidArgumentException('Unable to infer event type from listener Closure.');
    }

    /**
     * @return list<string>
     */
    protected function resolveNamedType(\ReflectionNamedType $type): array
    {
        if ($type->isBuiltin()) {
            return [];
        }

        $name = $type->getName();

        return $name === '' ? [] : [$name];
    }
}
