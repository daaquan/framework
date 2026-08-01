<?php

namespace Phare\Eloquent\Concerns;

use DateTime;
use DateTimeInterface;

trait HasTimestamps
{
    protected const CREATED_AT = 'created_at';

    protected const UPDATED_AT = 'updated_at';

    public bool $timestamps = true;

    protected ?string $dateFormat = null;

    public function initializeHasTimestamps(): void {}

    public function freshTimestamp(): DateTimeInterface
    {
        return new DateTime();
    }

    public function freshTimestampString(): string
    {
        return (string)$this->fromDateTime($this->freshTimestamp(), true);
    }

    public function usesTimestamps(): bool
    {
        return $this->timestamps && !static::isIgnoringTouch(static::class);
    }

    public function getCreatedAtColumn(): ?string
    {
        return defined('static::CREATED_AT') ? static::CREATED_AT : null;
    }

    public function getUpdatedAtColumn(): ?string
    {
        return defined('static::UPDATED_AT') ? static::UPDATED_AT : null;
    }

    public function setCreatedAt(mixed $value): static
    {
        $column = $this->getCreatedAtColumn();

        if ($column !== null) {
            $this->{$column} = $value;
        }

        return $this;
    }

    public function setUpdatedAt(mixed $value): static
    {
        $column = $this->getUpdatedAtColumn();

        if ($column !== null) {
            $this->{$column} = $value;
        }

        return $this;
    }

    public function updateTimestamps(): static
    {
        if (!$this->usesTimestamps()) {
            return $this;
        }

        $time = $this->freshTimestamp();
        $updatedAtColumn = $this->getUpdatedAtColumn();

        if ($updatedAtColumn !== null && !$this->isDirty($updatedAtColumn)) {
            $this->setUpdatedAt($time);
        }

        $createdAtColumn = $this->getCreatedAtColumn();

        if (!$this->exists && $createdAtColumn !== null && !$this->isDirty($createdAtColumn)) {
            $this->setCreatedAt($time);
        }

        return $this;
    }

    public function touch(array|string|null $attribute = null): bool
    {
        if (static::isIgnoringTouch(static::class)) {
            return false;
        }

        if ($attribute !== null) {
            $time = $this->freshTimestamp();

            foreach ((array)$attribute as $column) {
                $this->{$column} = $time;
            }

            return $this->save();
        }

        if (!$this->usesTimestamps()) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }

    public function touchQuietly(array|string|null $attribute = null): bool
    {
        return static::withoutEvents(fn (): bool => $this->touch($attribute));
    }

    protected function getDateFormat(): string
    {
        return $this->dateFormat ?? 'Y-m-d H:i:s';
    }
}
