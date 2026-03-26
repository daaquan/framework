<?php

namespace Phare\Eloquent\Relations;

class MorphPivot extends Pivot
{
    protected ?string $morphType = null;

    protected string|int|null $morphClass = null;

    public function setMorphType(string $morphType): static
    {
        $this->morphType = $morphType;

        return $this;
    }

    public function setMorphClass(string|int $morphClass): static
    {
        $this->morphClass = $morphClass;

        return $this;
    }

    public function getDeleteQuery(): array
    {
        $query = parent::getDeleteQuery();

        if ($this->morphType === null || $this->morphClass === null) {
            return $query;
        }

        $query['sql'] .= sprintf(' AND %s = ?', $this->morphType);
        $query['bindings'][] = $this->morphClass;

        return $query;
    }
}
