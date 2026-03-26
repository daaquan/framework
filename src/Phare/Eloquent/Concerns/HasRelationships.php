<?php

namespace Phare\Eloquent\Concerns;

use Phalcon\Di\DiInterface;
use Phare\Collections\Collection;
use Phare\Collections\Str;
use Phare\Eloquent\Builder;
use Phare\Eloquent\BuilderInterface;
use Phare\Eloquent\Model;
use Phare\Eloquent\Relations\BelongsToMany;
use Phare\Eloquent\Relations\BelongsTo;
use Phare\Eloquent\Relations\HasMany;
use Phare\Eloquent\Relations\HasManyThrough;
use Phare\Eloquent\Relations\HasOne;
use Phare\Eloquent\Relations\HasOneThrough;
use Phare\Eloquent\Relations\MorphedByMany;
use Phare\Eloquent\Relations\MorphMany;
use Phare\Eloquent\Relations\MorphOne;
use Phare\Eloquent\Relations\MorphToMany;
use Phare\Eloquent\Relations\MorphTo;
use Phare\Eloquent\Relations\Relation;

trait HasRelationships
{
    /**
     * @var array<string, mixed>
     */
    protected array $loadedRelations = [];

    public static function with($relations): BuilderInterface
    {
        return static::query()->with($relations);
    }

    public function load($relations): static
    {
        foreach ($this->normalizeRelations($relations) as $name => $constraints) {
            $relation = $this->$name();

            if (!$relation instanceof Relation) {
                throw new \RuntimeException(sprintf(
                    'Relationship method [%s] on model [%s] must return a relation instance.',
                    $name,
                    static::class
                ));
            }

            if ($constraints instanceof \Closure) {
                $constraints($relation);
            }

            $this->setRelation($name, $relation->getResults());
        }

        return $this;
    }

    public function hasOne($fields, $referenceModel = null, $referencedFields = null, array $options = []): HasOne|\Phalcon\Mvc\Model\Relation
    {
        if (!is_string($fields) || !class_exists($fields) || ($referenceModel !== null && is_string($referenceModel) && class_exists($referenceModel))) {
            return parent::hasOne($fields, $referenceModel, $referencedFields, $options);
        }

        $instance = $this->newRelatedInstance($fields);

        return new HasOne(
            $instance->newQuery(),
            $this,
            $referenceModel ?? $this->getForeignKey(),
            $referencedFields ?? $this->getKeyName()
        );
    }

    public function hasMany($fields, $referenceModel = null, $referencedFields = null, array $options = []): HasMany|\Phalcon\Mvc\Model\Relation
    {
        if (!is_string($fields) || !class_exists($fields) || ($referenceModel !== null && is_string($referenceModel) && class_exists($referenceModel))) {
            return parent::hasMany($fields, $referenceModel, $referencedFields, $options);
        }

        $instance = $this->newRelatedInstance($fields);

        return new HasMany(
            $instance->newQuery(),
            $this,
            $referenceModel ?? $this->getForeignKey(),
            $referencedFields ?? $this->getKeyName()
        );
    }

    public function belongsTo($fields, $referenceModel = null, $referencedFields = null, array $options = []): BelongsTo|\Phalcon\Mvc\Model\Relation
    {
        if (!is_string($fields) || !class_exists($fields) || ($referenceModel !== null && is_string($referenceModel) && class_exists($referenceModel))) {
            return parent::belongsTo($fields, $referenceModel, $referencedFields, $options);
        }

        $relation = $options['relation'] ?? $this->guessBelongsToRelation();

        $instance = $this->newRelatedInstance($fields);

        return new BelongsTo(
            $instance->newQuery(),
            $this,
            $referenceModel ?? Str::snake($relation) . '_' . $instance->getKeyName(),
            $referencedFields ?? $instance->getKeyName(),
            $relation
        );
    }

    public function morphMany(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): MorphMany
    {
        $instance = $this->newRelatedInstance($related);
        [$type, $id] = $this->getMorphs($name, $type, $id);

        return new MorphMany(
            $instance->newQuery(),
            $this,
            $id,
            $localKey ?? $this->getKeyName(),
            $type,
            $this->getMorphClass()
        );
    }

    public function morphOne(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): MorphOne
    {
        $instance = $this->newRelatedInstance($related);
        [$type, $id] = $this->getMorphs($name, $type, $id);

        return new MorphOne(
            $instance->newQuery(),
            $this,
            $id,
            $localKey ?? $this->getKeyName(),
            $type,
            $this->getMorphClass()
        );
    }

    public function morphTo(?string $name = null, ?string $type = null, ?string $id = null, ?string $ownerKey = null): MorphTo
    {
        $name ??= $this->guessBelongsToRelation();
        [$type, $id] = $this->getMorphs($name, $type, $id);
        $related = $this->readAttribute($type);
        $relatedClass = is_string($related) ? Model::getActualClassNameForMorph($related) : null;

        $query = $relatedClass && class_exists($relatedClass)
            ? $this->newRelatedInstance($relatedClass)->newQuery()
            : $this->newQuery();

        return new MorphTo(
            $query,
            $this,
            $type,
            $id,
            $ownerKey ?? 'id',
            $name
        );
    }

    public function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
        ?string $relation = null
    ): BelongsToMany {
        $relation ??= $this->guessBelongsToManyRelation();
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey ??= $this->getForeignKey();
        $relatedPivotKey ??= $instance->getForeignKey();
        $table ??= $this->joiningTable($related, $instance);

        return new BelongsToMany(
            $instance->newQuery(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?? $this->getKeyName(),
            $relatedKey ?? $instance->getKeyName(),
            $relation
        );
    }

    public function morphToMany(
        string $related,
        string $name,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
        ?string $relation = null,
        bool $inverse = false
    ): MorphToMany {
        $relation ??= $this->guessBelongsToManyRelation();
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey ??= $name . '_id';
        $relatedPivotKey ??= $instance->getForeignKey();
        $table ??= Str::snake($name) . 's';

        return new MorphToMany(
            $instance->newQuery(),
            $this,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?? $this->getKeyName(),
            $relatedKey ?? $instance->getKeyName(),
            $relation,
            $inverse
        );
    }

    public function morphedByMany(
        string $related,
        string $name,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
        ?string $relation = null
    ): MorphedByMany {
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey ??= $this->getForeignKey();
        $relatedPivotKey ??= $name . '_id';
        $table ??= Str::snake($name) . 's';
        $relation ??= $this->guessBelongsToManyRelation();

        return new MorphedByMany(
            $instance->newQuery(),
            $this,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?? $this->getKeyName(),
            $relatedKey ?? $instance->getKeyName(),
            $relation
        );
    }

    public function hasOneThrough(
        $fields,
        $intermediateModel,
        $intermediateFields = null,
        $intermediateReferencedFields = null,
        $referenceModel = null,
        $referencedFields = null,
        array $options = []
    ): HasOneThrough|\Phalcon\Mvc\Model\Relation {
        if (
            !is_string($fields)
            || !class_exists($fields)
            || !is_string($intermediateModel)
            || !class_exists($intermediateModel)
            || ($referenceModel !== null && is_string($referenceModel) && class_exists($referenceModel))
        ) {
            return parent::hasOneThrough(
                $fields,
                $intermediateModel,
                $intermediateFields,
                $intermediateReferencedFields,
                $referenceModel,
                $referencedFields,
                $options
            );
        }

        $relatedInstance = $this->newRelatedInstance($fields);
        $throughInstance = $this->newRelatedInstance($intermediateModel);

        return new HasOneThrough(
            $relatedInstance->newQuery(),
            $this,
            $throughInstance,
            $intermediateFields ?? $this->getForeignKey(),
            $intermediateReferencedFields ?? $throughInstance->getForeignKey(),
            $referenceModel ?? $this->getKeyName(),
            $referencedFields ?? $throughInstance->getKeyName()
        );
    }

    public function hasManyThrough(
        string $related,
        string $through,
        ?string $firstKey = null,
        ?string $secondKey = null,
        ?string $localKey = null,
        ?string $secondLocalKey = null
    ): HasManyThrough {
        $relatedInstance = $this->newRelatedInstance($related);
        $throughInstance = $this->newRelatedInstance($through);

        return new HasManyThrough(
            $relatedInstance->newQuery(),
            $this,
            $throughInstance,
            $firstKey ?? $this->getForeignKey(),
            $secondKey ?? $throughInstance->getForeignKey(),
            $localKey ?? $this->getKeyName(),
            $secondLocalKey ?? $throughInstance->getKeyName()
        );
    }

    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->loadedRelations);
    }

    public function getRelation(string $key): mixed
    {
        return $this->loadedRelations[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRelations(): array
    {
        return $this->loadedRelations;
    }

    public function setRelation(string $relation, mixed $value): static
    {
        $this->loadedRelations[$relation] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $relations
     */
    public function setRelations(array $relations): static
    {
        $this->loadedRelations = $relations;

        return $this;
    }

    public function unsetRelation(string $relation): static
    {
        unset($this->loadedRelations[$relation]);

        return $this;
    }

    public function getRelationshipFromMethod(string $method): mixed
    {
        $relation = $this->$method();

        if (!$relation instanceof Relation) {
            throw new \RuntimeException(sprintf(
                'Relationship method [%s] on model [%s] must return a relation instance.',
                $method,
                static::class
            ));
        }

        return $this->setRelation($method, $relation->getResults())->getRelation($method);
    }

    public function newQuery(?DiInterface $container = null): BuilderInterface
    {
        return (new Builder($container ?? $this->getDI()))->setModelName(static::class);
    }

    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    public function getKeyName()
    {
        return $this->primaryKey;
    }

    public function getKey()
    {
        $keyName = $this->getKeyName();

        return $this->$keyName;
    }

    public function getTable(): string
    {
        return $this->table ?? Str::tableize(class_basename(static::class));
    }

    public function qualifyColumn(string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    public function getForeignKey(): string
    {
        return Str::snake(class_basename(static::class)) . '_' . $this->getKeyName();
    }

    public function joiningTable(string $related, ?Model $instance = null): string
    {
        $segments = [
            $instance?->joiningTableSegment() ?? Str::snake(class_basename($related)),
            $this->joiningTableSegment(),
        ];

        sort($segments);

        return strtolower(implode('_', $segments));
    }

    public function joiningTableSegment(): string
    {
        return Str::snake(class_basename(static::class));
    }

    protected function newRelatedInstance(string $related): Model
    {
        /** @var Model $instance */
        $instance = new $related();

        if ($this->getDI() !== null) {
            $instance->setDI($this->getDI());
        }

        return $instance;
    }

    protected function guessBelongsToRelation(): string
    {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'] ?? 'relation';
    }

    protected function guessBelongsToManyRelation(): string
    {
        $ignored = ['belongsToMany', 'morphToMany', 'morphedByMany', 'guessBelongsToManyRelation'];

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $trace) {
            $function = $trace['function'] ?? null;

            if ($function !== null && !in_array($function, $ignored, true)) {
                return $function;
            }
        }

        return 'relation';
    }

    protected function getMorphs(string $name, ?string $type = null, ?string $id = null): array
    {
        return [$type ?? $name . '_type', $id ?? $name . '_id'];
    }

    /**
     * @return array<string, \Closure|null>
     */
    protected function normalizeRelations($relations): array
    {
        if (is_string($relations)) {
            return [$relations => null];
        }

        $normalized = [];

        foreach ((array)$relations as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = null;
                continue;
            }

            $normalized[$key] = $value instanceof \Closure ? $value : null;
        }

        return $normalized;
    }
}
