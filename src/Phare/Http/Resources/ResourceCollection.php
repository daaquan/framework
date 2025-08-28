<?php

namespace Phare\Http\Resources;

use Phare\Collections\Collection;
use Phare\Contracts\Support\Arrayable;
use Phare\Pagination\LengthAwarePaginator;
use Phare\Pagination\Paginator;

class ResourceCollection extends JsonResource implements \IteratorAggregate, \Countable
{
    public string $collects;
    protected iterable $collection;

    public function __construct(iterable $resource, ?string $collects = null)
    {
        parent::__construct($resource);
        
        $this->collection = $resource;
        $this->collects = $collects ?? $this->guessResourceClass();
    }

    protected function guessResourceClass(): string
    {
        $class = get_class($this);
        
        if (str_ends_with($class, 'Collection')) {
            return substr($class, 0, -10);
        }

        return $class . 'Resource';
    }

    public function toArray(): array
    {
        return $this->collection instanceof Collection 
            ? $this->collection->map([$this, 'mapIntoResource'])->toArray()
            : array_map([$this, 'mapIntoResource'], $this->collection);
    }

    public function mapIntoResource(mixed $item): array
    {
        if ($item instanceof JsonResource) {
            return $item->resolve();
        }

        if (class_exists($this->collects)) {
            return (new $this->collects($item))->resolve();
        }

        return is_array($item) ? $item : (array) $item;
    }

    protected function collectResource(iterable $resource): Collection
    {
        if ($resource instanceof Collection) {
            return $resource;
        }

        return new Collection(is_array($resource) ? $resource : iterator_to_array($resource));
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        if ($this->isPaginated()) {
            return $this->paginationInformation() + [
                static::$wrap ?? 'data' => $data,
            ];
        }

        return static::$wrap ? [static::$wrap => $data] : $data;
    }

    protected function isPaginated(): bool
    {
        return $this->collection instanceof LengthAwarePaginator ||
               $this->collection instanceof Paginator;
    }

    protected function paginationInformation(): array
    {
        if ($this->collection instanceof LengthAwarePaginator) {
            return [
                'links' => $this->paginationLinks(),
                'meta' => $this->paginationMeta(),
            ];
        }

        return [];
    }

    protected function paginationLinks(): array
    {
        return [
            'first' => $this->collection->url(1),
            'last' => $this->collection->url($this->collection->lastPage()),
            'prev' => $this->collection->previousPageUrl(),
            'next' => $this->collection->nextPageUrl(),
        ];
    }

    protected function paginationMeta(): array
    {
        return [
            'current_page' => $this->collection->currentPage(),
            'from' => $this->collection->firstItem(),
            'last_page' => $this->collection->lastPage(),
            'path' => $this->collection->path ?? '',
            'per_page' => $this->collection->perPage(),
            'to' => $this->collection->lastItem(),
            'total' => $this->collection->total(),
        ];
    }

    public function getIterator(): \ArrayIterator
    {
        if ($this->collection instanceof Collection) {
            return $this->collection->getIterator();
        }

        return new \ArrayIterator(is_array($this->collection) ? $this->collection : iterator_to_array($this->collection));
    }

    public function count(): int
    {
        if ($this->collection instanceof Collection) {
            return $this->collection->count();
        }

        if ($this->collection instanceof \Countable) {
            return $this->collection->count();
        }

        return count(is_array($this->collection) ? $this->collection : iterator_to_array($this->collection));
    }
}