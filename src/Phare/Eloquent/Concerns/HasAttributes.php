<?php

namespace Phare\Eloquent\Concerns;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Phalcon\Di\Di;
use Phare\Collections\Collection;
use Phare\Collections\Str;
use Phare\Eloquent\Casts\Attribute;
use Phare\Eloquent\Casts\CastsAttributes;
use Phare\Eloquent\Casts\CastsInboundAttributes;
use Phare\Eloquent\Casts\Json;
use Phare\Encryption\Encrypter;

trait HasAttributes
{
    protected array $attributes = [];

    protected array $original = [];

    protected array $changes = [];

    protected array $classCastCache = [];

    protected array $attributeCastCache = [];

    protected array $casts = [];

    protected array $appends = [];

    protected static array $attributeMutatorCache = [];

    protected function initializeHasAttributes(): void
    {
        $this->casts = array_map(static fn ($cast) => is_string($cast) ? $cast : (string)$cast, $this->casts);
    }

    public function getAttribute(?string $key): mixed
    {
        if ($key === null || $key === '') {
            return null;
        }

        if (
            array_key_exists($key, $this->attributes)
            || $this->hasGetMutator($key)
            || $this->hasAttributeGetMutator($key)
            || $this->hasCast($key)
        ) {
            return $this->transformModelValue($key, $this->attributes[$key] ?? null);
        }

        if (method_exists($this, 'relationLoaded') && $this->relationLoaded($key)) {
            return $this->getRelation($key);
        }

        if ($this->isRelation($key)) {
            return $this->getRelationshipFromMethod($key);
        }

        return null;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        unset($this->attributeCastCache[$key], $this->classCastCache[$key]);

        if (property_exists($this, 'passwordAttributes') && in_array($key, $this->passwordAttributes, true) && $value !== null) {
            $info = is_string($value) ? password_get_info($value) : ['algo' => null];
            if (($info['algo'] ?? null) === null) {
                $value = password_hash((string)$value, PASSWORD_DEFAULT);
            }
        }

        if ($this->hasSetMutator($key)) {
            $result = $this->{'set' . Str::studly($key) . 'Attribute'}($value);

            if ($result === null) {
                return $this;
            }

            return $this->fillMutatedAttributeValue($key, $result);
        }

        if ($this->hasAttributeSetMutator($key)) {
            $attribute = $this->getAttributeMarkedMutator($key);
            $result = $attribute->set !== null
                ? ($attribute->set)($value, $this->attributes)
                : $value;

            return $this->fillMutatedAttributeValue($key, $result);
        }

        if ($this->hasCast($key)) {
            $value = $this->setCastAttribute($key, $value);
        }

        $this->attributes[$key] = $value;
        $this->writeRawAttribute($key, $value);

        return $this;
    }

    public function hasGetMutator(string $key): bool
    {
        return method_exists($this, 'get' . Str::studly($key) . 'Attribute');
    }

    public function hasSetMutator(string $key): bool
    {
        return method_exists($this, 'set' . Str::studly($key) . 'Attribute');
    }

    public function hasAttributeGetMutator(string $key): bool
    {
        $cache = static::$attributeMutatorCache[static::class] ??= [];

        if (!array_key_exists($key, $cache)) {
            $cache[$key] = method_exists($this, Str::camel($key))
                && $this->{Str::camel($key)}() instanceof Attribute;
            static::$attributeMutatorCache[static::class] = $cache;
        }

        return $cache[$key] && $this->getAttributeMarkedMutator($key)->get !== null;
    }

    public function hasAttributeSetMutator(string $key): bool
    {
        return $this->hasAttributeMutator($key) && $this->getAttributeMarkedMutator($key)->set !== null;
    }

    public function mergeCasts(array $casts): static
    {
        $this->casts = array_merge($this->casts, $casts);

        return $this;
    }

    public function getCasts(): array
    {
        return $this->casts;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = [];
        $this->attributeCastCache = [];
        $this->classCastCache = [];

        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
            $this->writeRawAttribute((string)$key, $value);
        }

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    public function syncOriginal(): static
    {
        $this->original = $this->attributes;

        return $this;
    }

    public function syncOriginalAttribute(string $attribute): static
    {
        $this->original[$attribute] = $this->attributes[$attribute] ?? null;

        return $this;
    }

    public function syncChanges(): static
    {
        $this->changes = $this->getDirty();

        return $this;
    }

    public function isDirty(array|string|null $attributes = null): bool
    {
        return $this->hasChanges($this->getDirty(), $attributes);
    }

    public function isClean(array|string|null $attributes = null): bool
    {
        return !$this->isDirty($attributes);
    }

    public function wasChanged(array|string|null $attributes = null): bool
    {
        return $this->hasChanges($this->changes, $attributes);
    }

    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || !$this->originalIsEquivalent($key, $value)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->toArray(), array_flip($keys));
    }

    public function attributesToArray(): array
    {
        $attributes = [];

        foreach ($this->getArrayableAttributes() as $key => $value) {
            $attributes[$key] = $this->mutateAttributeForArray($key, $value);
        }

        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        return $attributes;
    }

    protected function getArrayableAttributes(): array
    {
        return $this->getArrayableItems($this->attributes);
    }

    protected function getArrayableAppends(): array
    {
        return array_values(array_filter(
            $this->appends,
            fn (string $key) => $this->isArrayable($key)
        ));
    }

    protected function getArrayableItems(array $values): array
    {
        if (property_exists($this, 'visible') && $this->visible !== []) {
            $values = array_intersect_key($values, array_flip($this->visible));
        }

        if (property_exists($this, 'hidden') && $this->hidden !== []) {
            $values = array_diff_key($values, array_flip($this->hidden));
        }

        return $values;
    }

    protected function isArrayable(string $key): bool
    {
        if (property_exists($this, 'visible') && $this->visible !== [] && !in_array($key, $this->visible, true)) {
            return false;
        }

        if (property_exists($this, 'hidden') && in_array($key, $this->hidden, true)) {
            return false;
        }

        return true;
    }

    protected function mutateAttributeForArray(string $key, mixed $value): mixed
    {
        $value = $this->getAttribute($key);

        if ($value instanceof DateTimeInterface) {
            return $value->format(str_contains((string)($this->casts[$key] ?? ''), 'date') ? 'Y-m-d H:i:s' : 'Y-m-d H:i:s');
        }

        if ($value instanceof Collection) {
            return $value->toArray();
        }

        if ($value instanceof \ArrayObject) {
            return $value->getArrayCopy();
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return $value;
    }

    protected function relationsToArray(): array
    {
        if (!method_exists($this, 'getRelations')) {
            return [];
        }

        $relations = [];

        foreach ($this->getArrayableItems($this->getRelations()) as $name => $relation) {
            if ($relation instanceof self) {
                $relations[$name] = $relation->toArray();

                continue;
            }

            if ($relation instanceof Collection) {
                $relations[$name] = array_map(
                    static fn ($item) => $item instanceof self ? $item->toArray() : $item,
                    $relation->toArray()
                );

                continue;
            }

            $relations[$name] = $relation;
        }

        return $relations;
    }

    protected function transformModelValue(string $key, mixed $value): mixed
    {
        if ($this->hasGetMutator($key)) {
            return $this->{'get' . Str::studly($key) . 'Attribute'}($value);
        }

        if ($this->hasAttributeGetMutator($key)) {
            $attribute = $this->getAttributeMarkedMutator($key);

            if ($attribute->shouldCache() && array_key_exists($key, $this->attributeCastCache)) {
                return $this->attributeCastCache[$key];
            }

            $value = $attribute->get !== null
                ? ($attribute->get)($value, $this->attributes)
                : $value;

            if ($attribute->shouldCache()) {
                $this->attributeCastCache[$key] = $value;
            }

            return $value;
        }

        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $castType = $this->getCastType($key);

        if ($this->isEnumCastable($castType)) {
            return $castType::from($value);
        }

        if ($this->isCustomCast($castType)) {
            if (array_key_exists($key, $this->classCastCache)) {
                return $this->classCastCache[$key];
            }

            $caster = $this->resolveCasterClass($castType);
            $casted = $caster instanceof CastsAttributes
                ? $caster->get($this, $key, $value, $this->attributes)
                : $value;

            $this->classCastCache[$key] = $casted;

            return $casted;
        }

        return match (true) {
            in_array($castType, ['int', 'integer'], true) => (int)$value,
            in_array($castType, ['real', 'float', 'double'], true) => (float)$value,
            $castType === 'string' => (string)$value,
            in_array($castType, ['bool', 'boolean'], true) => (bool)$value,
            $castType === 'object' => Json::decode($value, false),
            in_array($castType, ['array', 'json'], true) => Json::decode($value, true),
            $castType === 'collection' => new Collection((array)Json::decode($value, true)),
            $castType === 'date' => $this->asDate($value),
            in_array($castType, ['datetime', 'timestamp'], true) => $this->asDateTime($value),
            $castType === 'immutable_date' => $this->asDateImmutable($value, false),
            $castType === 'immutable_datetime' => $this->asDateImmutable($value, true),
            str_starts_with($castType, 'decimal:') => number_format((float)$value, (int)Str::after($castType, ':'), '.', ''),
            str_starts_with($castType, 'encrypted') => $this->fromEncryptedCast($castType, $value),
            default => $value,
        };
    }

    protected function setCastAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $castType = $this->getCastType($key);

        if ($this->isEnumCastable($castType)) {
            return $value instanceof BackedEnum ? $value->value : $value;
        }

        if ($this->isCustomCast($castType)) {
            $caster = $this->resolveCasterClass($castType);

            if ($caster instanceof CastsAttributes || $caster instanceof CastsInboundAttributes) {
                return $caster->set($this, $key, $value, $this->attributes);
            }

            return $value;
        }

        return match (true) {
            in_array($castType, ['int', 'integer'], true) => (int)$value,
            in_array($castType, ['real', 'float', 'double'], true) => (float)$value,
            $castType === 'string' => (string)$value,
            in_array($castType, ['bool', 'boolean'], true) => (bool)$value,
            $castType === 'object' => Json::encode($value),
            in_array($castType, ['array', 'json'], true) => Json::encode($value),
            $castType === 'collection' => Json::encode($value instanceof Collection ? $value->toArray() : $value),
            $castType === 'date' => $this->fromDateTime($value, false),
            in_array($castType, ['datetime', 'immutable_datetime', 'timestamp'], true) => $this->fromDateTime($value, true),
            $castType === 'immutable_date' => $this->fromDateTime($value, false),
            str_starts_with($castType, 'decimal:') => number_format((float)$value, (int)Str::after($castType, ':'), '.', ''),
            str_starts_with($castType, 'encrypted') => $this->toEncryptedCast($castType, $value),
            default => $value,
        };
    }

    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    protected function getCastType(string $key): string
    {
        return trim((string)($this->casts[$key] ?? ''));
    }

    protected function asDate(mixed $value): ?\DateTime
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTime) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return new \DateTime($value->format('Y-m-d H:i:s'));
        }

        if (is_numeric($value)) {
            return new \DateTime('@' . $value);
        }

        return new \DateTime((string)$value);
    }

    protected function asDateTime(mixed $value): ?\DateTime
    {
        return $this->asDate($value);
    }

    protected function asDateImmutable(mixed $value, bool $withTime): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return new DateTimeImmutable($value->format($withTime ? 'Y-m-d H:i:s' : 'Y-m-d'));
        }

        if (is_numeric($value)) {
            return new DateTimeImmutable('@' . $value);
        }

        return new DateTimeImmutable((string)$value);
    }

    protected function fromDateTime(mixed $value, bool $withTime): mixed
    {
        if (!$value instanceof DateTimeInterface) {
            return $value;
        }

        if ($withTime && method_exists($this, 'getDateFormat')) {
            return $value->format($this->getDateFormat());
        }

        return $value->format($withTime ? 'Y-m-d H:i:s' : 'Y-m-d');
    }

    protected function fromEncryptedCast(string $castType, mixed $value): mixed
    {
        $decrypted = $this->resolveAttributeEncrypter()->decryptString((string)$value);

        return match ($castType) {
            'encrypted' => $decrypted,
            'encrypted:array' => Json::decode($decrypted, true),
            'encrypted:collection' => new Collection((array)Json::decode($decrypted, true)),
            'encrypted:object' => Json::decode($decrypted, false),
            default => $decrypted,
        };
    }

    protected function toEncryptedCast(string $castType, mixed $value): string
    {
        $payload = match ($castType) {
            'encrypted' => (string)$value,
            'encrypted:array', 'encrypted:collection', 'encrypted:object' => Json::encode(
                $value instanceof Collection ? $value->toArray() : $value
            ),
            default => (string)$value,
        };

        return $this->resolveAttributeEncrypter()->encryptString((string)$payload);
    }

    protected function fillMutatedAttributeValue(string $key, mixed $result): static
    {
        if (is_array($result)) {
            foreach ($result as $attribute => $attributeValue) {
                $this->attributes[$attribute] = $attributeValue;
                $this->writeRawAttribute((string)$attribute, $attributeValue);
            }

            return $this;
        }

        $this->attributes[$key] = $result;
        $this->writeRawAttribute($key, $result);

        return $this;
    }

    protected function hasAttributeMutator(string $key): bool
    {
        $cache = static::$attributeMutatorCache[static::class] ??= [];

        if (!array_key_exists($key, $cache)) {
            $cache[$key] = method_exists($this, Str::camel($key))
                && $this->{Str::camel($key)}() instanceof Attribute;
            static::$attributeMutatorCache[static::class] = $cache;
        }

        return $cache[$key];
    }

    protected function getAttributeMarkedMutator(string $key): Attribute
    {
        $method = Str::camel($key);

        return $this->$method();
    }

    protected function isRelation(string $key): bool
    {
        if (!method_exists($this, $key)) {
            return false;
        }

        if ($this->hasAttributeMutator($key) || $this->hasGetMutator($key)) {
            return false;
        }

        return true;
    }

    protected function resolveCasterClass(string $castType): object
    {
        [$class, $argumentString] = array_pad(explode(':', $castType, 2), 2, null);
        $arguments = $argumentString === null || $argumentString === '' ? [] : explode(',', $argumentString);

        return new $class(...$arguments);
    }

    protected function isCustomCast(string $castType): bool
    {
        [$class] = explode(':', $castType, 2);

        return class_exists($class) && (
            is_subclass_of($class, CastsAttributes::class)
            || is_subclass_of($class, CastsInboundAttributes::class)
        );
    }

    protected function isEnumCastable(string $castType): bool
    {
        return enum_exists($castType) && is_subclass_of($castType, BackedEnum::class);
    }

    protected function originalIsEquivalent(string $key, mixed $current): bool
    {
        return ($this->original[$key] ?? null) === $current;
    }

    protected function hasChanges(array $changes, array|string|null $attributes = null): bool
    {
        if ($attributes === null) {
            return $changes !== [];
        }

        foreach ((array)$attributes as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }

    protected function writeRawAttribute(string $key, mixed $value): void
    {
        unset($this->{$key});
    }

    /**
     * Models used to carry a second copy of their data inside Phalcon's active
     * record (its snapshot). Phare owns hydration now, so $attributes is the
     * only store and there is nothing to refresh but the cast caches.
     */
    protected function refreshAttributeState(): void
    {
        $this->attributeCastCache = [];
        $this->classCastCache = [];
    }

    protected function syncAttributesToStorage(): void
    {
        // No shadow storage to sync to any more; see refreshAttributeState().
    }

    protected function resolveAttributeEncrypter(): Encrypter
    {
        $config = null;
        $di = Di::getDefault();

        if ($di !== null && method_exists($di, 'has') && $di->has('config')) {
            $config = $di->getShared('config');
        }

        $cipher = strtolower((string)($config?->path('app.cipher') ?? 'aes-256-cbc'));
        $rawKey = (string)($config?->path('app.key') ?? '');

        return new Encrypter($this->normalizeEncryptionKey($rawKey, $cipher), $cipher);
    }

    protected function normalizeEncryptionKey(string $key, string $cipher): string
    {
        if ($key !== '' && str_contains($key, ':')) {
            [$method, $payload] = explode(':', $key, 2);
            $decoder = $method . '_decode';

            if (function_exists($decoder)) {
                $decoded = $decoder($payload);

                if (is_string($decoded) && $decoded !== '') {
                    return $decoded;
                }
            }
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(Str::after($key, 'base64:'), true);

            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        $decoded = base64_decode($key, true);

        if (is_string($decoded) && $decoded !== '') {
            return $decoded;
        }

        $size = str_contains($cipher, '128') ? 16 : 32;
        $fallback = $key !== '' ? $key : 'phare-attribute-cast-key';

        return substr(str_pad($fallback, $size, '0'), 0, $size);
    }
}
