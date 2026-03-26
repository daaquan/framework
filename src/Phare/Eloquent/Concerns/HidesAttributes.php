<?php

namespace Phare\Eloquent\Concerns;

trait HidesAttributes
{
    protected array $hidden = [];

    protected array $visible = [];

    public function getHidden(): array
    {
        return $this->hidden;
    }

    public function setHidden(array $hidden): static
    {
        $this->hidden = array_values($hidden);

        return $this;
    }

    public function getVisible(): array
    {
        return $this->visible;
    }

    public function setVisible(array $visible): static
    {
        $this->visible = array_values($visible);

        return $this;
    }

    public function makeVisible(array|string $attributes): static
    {
        $attributes = is_array($attributes) ? $attributes : [$attributes];
        $this->visible = array_values(array_unique([...$this->visible, ...$attributes]));
        $this->hidden = array_values(array_diff($this->hidden, $attributes));

        return $this;
    }

    public function makeVisibleIf(bool|\Closure $condition, array|string $attributes): static
    {
        $condition = $condition instanceof \Closure ? $condition($this) : $condition;

        return $condition ? $this->makeVisible($attributes) : $this;
    }

    public function makeHidden(array|string $attributes): static
    {
        $attributes = is_array($attributes) ? $attributes : [$attributes];
        $this->hidden = array_values(array_unique([...$this->hidden, ...$attributes]));
        $this->visible = array_values(array_diff($this->visible, $attributes));

        return $this;
    }

    public function makeHiddenIf(bool|\Closure $condition, array|string $attributes): static
    {
        $condition = $condition instanceof \Closure ? $condition($this) : $condition;

        return $condition ? $this->makeHidden($attributes) : $this;
    }
}
