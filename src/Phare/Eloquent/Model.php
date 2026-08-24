<?php

namespace Phare\Eloquent;

use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;
use Phare\Collections\Collection;
use Phare\Collections\Str;
use Phare\Database\Connection;
use Phare\Database\MySql\DatabaseManager;
use Phare\Eloquent\Concerns\GuardsAttributes;
use Phare\Eloquent\Concerns\HasAttributes;
use Phare\Eloquent\Concerns\HasEvents;
use Phare\Eloquent\Concerns\HasGlobalScopes;
use Phare\Eloquent\Concerns\HasRelationships;
use Phare\Eloquent\Concerns\HidesAttributes;
use Phare\Eloquent\Exceptions\ModelNotFoundException;

/**
 * Phare's model.
 *
 * It used to extend Phalcon\Mvc\Model, which put Phalcon's entire active
 * record on every Phare model and made Phalcon's PHQL engine the only way to
 * run a query. Persistence is done here now, against a
 * Phare\Database\Connection.
 */
#[\AllowDynamicProperties]
class Model implements \ArrayAccess
{
    use GuardsAttributes;
    use HasAttributes;
    use HasEvents;
    use HasGlobalScopes;
    use HasRelationships;
    use HidesAttributes;

    /**
     * @var array<class-string, bool>
     */
    protected static array $booted = [];

    /**
     * @var array<class-string, bool>
     */
    protected static array $initializing = [];

    /**
     * @var array<class-string, array<int, string>>
     */
    protected static array $traitInitializers = [];

    /**
     * @var array<string, class-string>
     */
    protected static array $morphMap = [];

    protected ?string $connection = null;

    protected ?string $table = null;

    protected string $primaryKey = 'id';

    protected array $passwordAttributes = [];

    protected array $dispatchesEvents = [];

    protected bool $exists = false;

    protected ?DiInterface $container = null;

    public function __construct(?DiInterface $container = null)
    {
        $this->container = $container;

        $this->initialize();
    }

    public function setDI(DiInterface $container): void
    {
        $this->container = $container;
    }

    public function getDI(): ?DiInterface
    {
        return $this->container ??= Di::getDefault();
    }

    protected function initialize(): void
    {
        $this->bootIfNotBooted();

        if ($this->table === null) {
            $this->table = Str::tableize(class_basename(get_class($this)));
        }

        $this->setupConnectionService();

        $this->initializeTraits();
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    protected function setupConnectionService(): void
    {
        $di = $this->getDI();

        if ($di === null || !$di->has('dbManager')) {
            return;
        }

        /** @var DatabaseManager $dbManager */
        $dbManager = $di->getShared('dbManager');

        $this->connection = self::resolveConnectionName($dbManager, $this->connection, get_class($this));
    }

    /** The DI service name of this model's connection. */
    public function getWriteConnectionService(): string
    {
        return $this->connection ?? 'db';
    }

    public static function resolveConnectionName(DatabaseManager $dbManager, ?string $current, string $className): string
    {
        if ($current !== null) {
            return $current;
        }

        $fragments = explode('\\', $className);
        $serviceName = count($fragments) >= 2
            ? strtolower($fragments[count($fragments) - 2])
            : null;

        if ($serviceName !== null && $dbManager->hasConnectionService($serviceName)) {
            return $serviceName;
        }

        if ($dbManager->hasConnectionService('db')) {
            return 'db';
        }

        return $dbManager->getDefaultConnection();
    }

    public function create(?array $attributes = null): bool
    {
        if ($attributes !== null) {
            $this->fill($attributes);
        }

        if ($this->fireModelEvent('saving', true) === false || $this->fireModelEvent('creating', true) === false) {
            return false;
        }

        $dirtyBeforeSave = $this->getDirty();
        $this->applyTimestampColumns();

        $attributes = $this->getAttributesForPersistence();
        $created = $this->getQueryConnection()->insert($this->getTable(), $attributes);

        if ($created) {
            $keyName = $this->getKeyName();

            if (!array_key_exists($keyName, $attributes) || $attributes[$keyName] === null) {
                $id = $this->getQueryConnection()->lastInsertId();

                if ($id !== false && $id !== null && $id !== '0') {
                    $this->attributes[$keyName] = ctype_digit((string)$id) ? (int)$id : $id;
                }
            }

            $this->exists = true;
            $this->finishSave(['created', 'saved'], $dirtyBeforeSave);
        }

        return $created;
    }

    public function update(?array $attributes = null): bool
    {
        if ($attributes !== null) {
            $this->fill($attributes);
        }

        if ($this->fireModelEvent('saving', true) === false || $this->fireModelEvent('updating', true) === false) {
            return false;
        }

        $dirtyBeforeSave = $this->getDirty();
        $this->applyTimestampColumns();

        $dirty = $this->getDirty();
        $updated = $dirty === [] ? true : $this->performUpdate($dirty);

        if ($updated) {
            $this->exists = true;
            $this->finishSave(['updated', 'saved'], $dirtyBeforeSave);
        }

        return $updated;
    }

    public function save(?array $attributes = null): bool
    {
        if ($attributes !== null) {
            $this->fill($attributes);
        }

        if ($this->exists || $this->original !== [] || $this->getKey() !== null) {
            return $this->update();
        }

        return $this->create();
    }

    public function delete(): bool
    {
        if ($this->fireModelEvent('deleting', true) === false) {
            return false;
        }

        $key = $this->attributes[$this->getKeyName()] ?? $this->original[$this->getKeyName()] ?? null;

        if ($key === null) {
            return false;
        }

        $deleted = $this->getQueryConnection()->delete(
            $this->getTable(),
            $this->getKeyName() . ' = ?',
            [$key]
        );

        if ($deleted) {
            $this->fireModelEvent('deleted', false);
        }

        return $deleted;
    }

    public function fill(array $data): static
    {
        $attributes = $this->fillableFromArray($data);

        foreach ($attributes as $key => $value) {
            $this->setAttribute((string)$key, $value);
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes, ignoring $fillable/$guarded.
     */
    public function forceFill(array $data): static
    {
        foreach ($data as $key => $value) {
            $this->setAttribute((string)$key, $value);
        }

        return $this;
    }

    public function assign(array $data, $fillable = null, $dataColumnMap = null): static
    {
        if (is_array($dataColumnMap)) {
            $mapped = [];

            foreach ($data as $key => $value) {
                $mapped[$dataColumnMap[$key] ?? $key] = $value;
            }

            $data = $mapped;
        }

        $attributes = is_array($fillable) ? array_intersect_key($data, array_flip($fillable)) : $data;

        $this->fill($attributes);

        return $this;
    }

    /** @return Collection<int, static> */
    public static function all(array $columns = ['*']): Collection
    {
        return static::query()->columns($columns)->get();
    }

    /** @return Collection<int, static> */
    public static function find($parameters = null): Collection
    {
        return static::applyFindParameters(static::query(), $parameters)->get();
    }

    public static function findFirst($parameters = null)
    {
        return static::applyFindParameters(static::query(), $parameters)->first();
    }

    public static function first($id, array $columns = ['*'])
    {
        return static::findFirst([$id, 'columns' => implode(',', $columns)]);
    }

    public static function firstOrFail($id, $columns = ['*'])
    {
        $result = static::findFirst([$id, 'columns' => implode(',', $columns)]);

        if ($result === null) {
            throw new ModelNotFoundException('No query results for model [' . static::class . '] ' . $id);
        }

        return $result;
    }

    public function __get(string $property)
    {
        if ($property === '') {
            return;
        }

        if (
            array_key_exists($property, $this->getAttributes())
            || $this->hasGetMutator($property)
            || $this->hasAttributeGetMutator($property)
            || array_key_exists($property, $this->getCasts())
            || in_array($property, $this->appends, true)
            || $this->relationLoaded($property)
            || $this->isRelation($property)
        ) {
            return $this->getAttribute($property);
        }

    }

    public function __set(string $property, $value): void
    {
        $this->setAttribute($property, $value);
    }

    public function __isset(string $property): bool
    {
        if (array_key_exists($property, $this->attributes)) {
            return $this->attributes[$property] !== null;
        }

        if (
            $this->hasGetMutator($property)
            || $this->hasAttributeGetMutator($property)
            || array_key_exists($property, $this->getCasts())
            || $this->relationLoaded($property)
        ) {
            return $this->getAttribute($property) !== null;
        }

        return false;
    }

    public function writeAttribute(string $attribute, $value): void
    {
        $this->attributes[$attribute] = $value;
        unset($this->{$attribute});
    }

    public function readAttribute(string $attribute)
    {
        return $this->attributes[$attribute] ?? null;
    }

    public function toArray($columns = null, $useGetter = true): array
    {
        return array_merge($this->attributesToArray(), $this->relationsToArray());
    }

    public static function where(string $field, $operator = null, $value = null)
    {
        return static::query()->where($field, $operator, $value);
    }

    public static function query(?DiInterface $container = null): BuilderInterface
    {
        $model = new static();

        if ($container !== null) {
            $model->setDI($container);
        }

        return $model->newQuery($container);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->attributes)
            || $this->hasGetMutator((string)$offset)
            || $this->hasAttributeGetMutator((string)$offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->{$offset};
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->{$offset} = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset], $this->attributeCastCache[$offset], $this->classCastCache[$offset]);
        $this->writeAttribute((string)$offset, null);
    }

    protected function finishSave(array $events, array $dirtyBeforeSave = []): void
    {
        $this->changes = array_replace($dirtyBeforeSave, $this->getDirty());
        $this->syncOriginal();

        foreach ($events as $event) {
            $this->fireModelEvent($event, false);
        }
    }

    protected function getAttributesForPersistence(): array
    {
        $attributes = array_filter(
            $this->attributes,
            fn ($value, $key) => !($key === $this->getKeyName() && $value === null),
            ARRAY_FILTER_USE_BOTH
        );

        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->prepareValueForPersistence($value, (string)$key);
        }

        return $attributes;
    }

    protected function performUpdate(array $dirty): bool
    {
        $keyName = $this->getKeyName();
        $key = $this->original[$keyName] ?? $this->attributes[$keyName] ?? null;

        if ($key === null) {
            return false;
        }

        $columns = array_keys($dirty);
        $values = [];

        foreach ($dirty as $column => $value) {
            $values[] = $this->prepareValueForPersistence($value, (string)$column);
        }

        $assignments = implode(', ', array_map(
            static fn (string $column): string => $column . ' = ?',
            $columns
        ));

        return $this->getQueryConnection()->statement(
            sprintf('UPDATE %s SET %s WHERE %s = ?', $this->getTable(), $assignments, $keyName),
            [...$values, $key]
        );
    }

    protected function applyTimestampColumns(): void
    {
        if (method_exists($this, 'updateTimestamps')) {
            $this->updateTimestamps();
        }
    }

    protected function bootIfNotBooted(): void
    {
        if (isset(static::$booted[static::class])) {
            return;
        }

        static::boot();

        static::$booted[static::class] = true;
    }

    protected static function boot(): void
    {
        $class = static::class;
        $bootedMethods = [];

        static::$traitInitializers[$class] = [];

        foreach (static::classUsesRecursive($class) as $trait) {
            $baseName = class_basename($trait);
            $bootMethod = 'boot' . $baseName;
            $initializeMethod = 'initialize' . $baseName;

            if (method_exists($class, $bootMethod) && !in_array($bootMethod, $bootedMethods, true)) {
                forward_static_call([$class, $bootMethod]);
                $bootedMethods[] = $bootMethod;
            }

            if (method_exists($class, $initializeMethod)) {
                static::$traitInitializers[$class][] = $initializeMethod;
            }
        }

        static::$traitInitializers[$class] = array_values(array_unique(static::$traitInitializers[$class]));
    }

    protected function initializeTraits(): void
    {
        $class = static::class;

        if (isset(static::$initializing[$class])) {
            return;
        }

        static::$initializing[$class] = true;

        try {
            foreach (static::$traitInitializers[$class] ?? [] as $method) {
                $this->{$method}();
            }
        } finally {
            unset(static::$initializing[$class]);
        }
    }

    /**
     * The Phare-typed connection this model's queries run on.
     */
    public function getQueryConnection(): Connection
    {
        /** @var DatabaseManager $dbManager */
        $dbManager = $this->getDI()->getShared('dbManager');

        return Connection::wrap($dbManager->connection($this->connection));
    }

    public function newQuery(?DiInterface $container = null): BuilderInterface
    {
        return $this->registerGlobalScopes($this->newQueryWithoutScopes($container));
    }

    public function newModelQuery(?DiInterface $container = null): BuilderInterface
    {
        if ($container !== null && $this->getDI() === null) {
            $this->setDI($container);
        }

        return (new Builder())
            ->setModelName(static::class)
            ->setEloquentModel($this);
    }

    public function newQueryWithoutScopes(?DiInterface $container = null): BuilderInterface
    {
        return $this->newModelQuery($container);
    }

    public function markAsRetrieved(bool $refreshAttributes = false): static
    {
        if ($refreshAttributes) {
            $this->refreshAttributeState();
        }

        $this->exists = true;
        $this->syncOriginal();
        $this->changes = [];
        $this->fireModelEvent('retrieved', false);

        return $this;
    }

    public function hydrate(array $attributes): static
    {
        $this->setRawAttributes($attributes, true);

        return $this->markAsRetrieved();
    }

    protected function prepareValueForPersistence(mixed $value, ?string $key = null): mixed
    {
        if ($value instanceof \DateTimeInterface && method_exists($this, 'fromDateTime')) {
            $withTime = true;

            if ($key !== null && method_exists($this, 'hasCast') && $this->hasCast($key)) {
                $withTime = !in_array($this->getCastType($key), ['date', 'immutable_date'], true);
            }

            return $this->fromDateTime($value, $withTime);
        }

        return $value;
    }

    /**
     * @param array<string, class-string>|null $map
     * @return array<string, class-string>
     */
    public static function morphMap(?array $map = null, bool $merge = true): array
    {
        if ($map === null) {
            return static::$morphMap;
        }

        static::$morphMap = $merge
            ? array_merge(static::$morphMap, $map)
            : $map;

        return static::$morphMap;
    }

    public static function getActualClassNameForMorph(string $alias): string
    {
        return static::$morphMap[$alias] ?? $alias;
    }

    public function getMorphClass(): string
    {
        $alias = array_search(static::class, static::$morphMap, true);

        return $alias === false ? static::class : $alias;
    }

    public function registerGlobalScopes(BuilderInterface $builder): BuilderInterface
    {
        if (!$builder instanceof Builder) {
            return $builder;
        }

        foreach (static::getGlobalScopes() as $identifier => $scope) {
            $builder->withGlobalScope($identifier, $scope);
        }

        return $builder;
    }

    /**
     * @return array<int, class-string>
     */
    protected static function classUsesRecursive(string $class): array
    {
        $traits = [];

        do {
            $traits += class_uses($class) ?: [];
        } while ($class = get_parent_class($class));

        $search = array_values($traits);

        while ($search !== []) {
            $trait = array_pop($search);
            $nestedTraits = class_uses($trait) ?: [];

            foreach ($nestedTraits as $nestedTrait) {
                if (!isset($traits[$nestedTrait])) {
                    $traits[$nestedTrait] = $nestedTrait;
                    $search[] = $nestedTrait;
                }
            }
        }

        return array_values($traits);
    }

    protected static function applyFindParameters(BuilderInterface $builder, mixed $parameters): BuilderInterface
    {
        if ($parameters === null) {
            return $builder;
        }

        $key = (new static())->getKeyName();

        if (!is_array($parameters)) {
            return $builder->where($key, $parameters);
        }

        if (isset($parameters[0])) {
            $builder->where($key, $parameters[0]);
            unset($parameters[0]);
        }

        if (isset($parameters['conditions'])) {
            $builder->whereRaw($parameters['conditions'], $parameters['bind'] ?? []);
        }

        if (isset($parameters['columns'])) {
            $builder->columns($parameters['columns']);
        }

        if (isset($parameters['order'])) {
            $builder->orderBy($parameters['order']);
        }

        if (isset($parameters['group'])) {
            $builder->groupBy($parameters['group']);
        }

        if (isset($parameters['limit'])) {
            $limit = $parameters['limit'];

            if (is_array($limit)) {
                $builder->limit($limit['number'] ?? 0, $limit['offset'] ?? 0);
            } else {
                $builder->limit($limit);
            }
        }

        return $builder;
    }
}
