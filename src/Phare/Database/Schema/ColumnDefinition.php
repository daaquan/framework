<?php

namespace Phare\Database\Schema;

class ColumnDefinition
{
    protected string $type;
    protected string $name;
    protected array $attributes = [];

    public function __construct(string $type, string $name, array $parameters = [])
    {
        $this->type = $type;
        $this->name = $name;
        $this->attributes = $parameters;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    // Column modifiers
    public function nullable(bool $value = true): self
    {
        $this->attributes['nullable'] = $value;
        return $this;
    }

    public function default($value): self
    {
        $this->attributes['default'] = $value;
        return $this;
    }

    public function unsigned(): self
    {
        $this->attributes['unsigned'] = true;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->attributes['autoIncrement'] = true;
        return $this;
    }

    public function primary(): self
    {
        $this->attributes['primary'] = true;
        return $this;
    }

    public function unique(): self
    {
        $this->attributes['unique'] = true;
        return $this;
    }

    public function index(): self
    {
        $this->attributes['index'] = true;
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->attributes['comment'] = $comment;
        return $this;
    }

    public function after(string $column): self
    {
        $this->attributes['after'] = $column;
        return $this;
    }

    public function first(): self
    {
        $this->attributes['first'] = true;
        return $this;
    }

    public function charset(string $charset): self
    {
        $this->attributes['charset'] = $charset;
        return $this;
    }

    public function collation(string $collation): self
    {
        $this->attributes['collation'] = $collation;
        return $this;
    }

    public function change(): self
    {
        $this->attributes['change'] = true;
        return $this;
    }

    public function useCurrent(): self
    {
        $this->attributes['useCurrent'] = true;
        return $this;
    }

    public function useCurrentOnUpdate(): self
    {
        $this->attributes['useCurrentOnUpdate'] = true;
        return $this;
    }
}