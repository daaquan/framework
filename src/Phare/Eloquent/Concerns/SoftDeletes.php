<?php

namespace Phare\Eloquent\Concerns;

use Phare\Eloquent\SoftDeletingScope;

trait SoftDeletes
{
    protected const DELETED_AT = 'deleted_at';

    protected bool $forceDeleting = false;

    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(new SoftDeletingScope());
    }

    public function initializeSoftDeletes(): void
    {
        $this->casts[$this->getDeletedAtColumn()] ??= 'datetime';
    }

    public function restore(): bool
    {
        if ($this->fireModelEvent('restoring', true) === false) {
            return false;
        }

        $key = $this->deleteKeyValue();

        if ($key === null) {
            return false;
        }

        $columns = [$this->getDeletedAtColumn() => null];

        if (method_exists($this, 'usesTimestamps') && $this->usesTimestamps()) {
            $updatedAtColumn = $this->getUpdatedAtColumn();

            if ($updatedAtColumn !== null) {
                $columns[$updatedAtColumn] = $this->freshTimestampString();
            }
        }

        $assignments = implode(', ', array_map(
            static fn (string $column): string => $column . ' = ?',
            array_keys($columns)
        ));

        $restored = $this->getWriteConnection()->execute(
            sprintf('UPDATE %s SET %s WHERE %s = ?', $this->getTable(), $assignments, $this->getKeyName()),
            [...array_values($columns), $key]
        );

        if ($restored) {
            $this->attributes[$this->getDeletedAtColumn()] = null;
            parent::__set($this->getDeletedAtColumn(), null);

            if (isset($updatedAtColumn)) {
                $this->attributes[$updatedAtColumn] = $columns[$updatedAtColumn];
                parent::__set($updatedAtColumn, $columns[$updatedAtColumn]);
            }

            $this->fireModelEvent('restored', false);
        }

        return $restored;
    }

    public function delete(): bool
    {
        if ($this->fireModelEvent('deleting', true) === false) {
            return false;
        }

        $key = $this->deleteKeyValue();

        if ($key === null) {
            return false;
        }

        if ($this->forceDeleting) {
            $deleted = $this->getWriteConnection()->delete(
                $this->getTable(),
                $this->getKeyName() . ' = ?',
                [$key]
            );

            if ($deleted) {
                $this->fireModelEvent('deleted', false);
            }

            return $deleted;
        }

        $columns = [$this->getDeletedAtColumn() => $this->freshTimestampString()];

        if (method_exists($this, 'usesTimestamps') && $this->usesTimestamps()) {
            $updatedAtColumn = $this->getUpdatedAtColumn();

            if ($updatedAtColumn !== null) {
                $columns[$updatedAtColumn] = $columns[$this->getDeletedAtColumn()];
            }
        }

        $assignments = implode(', ', array_map(
            static fn (string $column): string => $column . ' = ?',
            array_keys($columns)
        ));

        $deleted = $this->getWriteConnection()->execute(
            sprintf('UPDATE %s SET %s WHERE %s = ?', $this->getTable(), $assignments, $this->getKeyName()),
            [...array_values($columns), $key]
        );

        if ($deleted) {
            foreach ($columns as $column => $value) {
                $this->attributes[$column] = $value;
                parent::__set($column, $value);
            }

            $this->fireModelEvent('deleted', false);
            $this->fireModelEvent('trashed', false);
        }

        return $deleted;
    }

    public function forceDelete(): bool
    {
        if ($this->fireModelEvent('forceDeleting', true) === false) {
            return false;
        }

        $this->forceDeleting = true;

        try {
            $deleted = $this->delete();

            if ($deleted) {
                $this->fireModelEvent('forceDeleted', false);
            }

            return $deleted;
        } finally {
            $this->forceDeleting = false;
        }
    }

    public function trashed(): bool
    {
        return $this->{$this->getDeletedAtColumn()} !== null;
    }

    public function getDeletedAtColumn(): string
    {
        return static::DELETED_AT;
    }

    public function getQualifiedDeletedAtColumn(): string
    {
        return $this->qualifyColumn($this->getDeletedAtColumn());
    }

    protected function deleteKeyValue(): mixed
    {
        return $this->readAttribute($this->getKeyName())
            ?? ($this->attributes[$this->getKeyName()] ?? null);
    }
}
