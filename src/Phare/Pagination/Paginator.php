<?php

namespace Phare\Pagination;

use Phare\Collections\Collection;
use Phare\Contracts\Support\Arrayable;
use Phare\Contracts\Support\Jsonable;

class Paginator implements \Countable, \IteratorAggregate, \JsonSerializable, Arrayable, Jsonable
{
    protected Collection $items;

    protected int $perPage;

    protected int $currentPage;

    protected array $options;

    protected ?string $path = null;

    protected array $query = [];

    protected ?string $fragment = null;

    protected ?string $pageName = 'page';

    public function __construct($items, int $perPage, ?int $currentPage = null, array $options = [])
    {
        $this->items = $items instanceof Collection ? $items : new Collection($items);
        $this->perPage = $perPage;
        $this->currentPage = $this->setCurrentPage($currentPage);
        $this->options = $options;

        $this->path = $this->options['path'] ?? $this->resolveCurrentPath();
        $this->pageName = $this->options['pageName'] ?? 'page';
    }

    protected function setCurrentPage(?int $currentPage): int
    {
        $currentPage = $currentPage ?: $this->resolveCurrentPage();

        return $this->isValidPageNumber($currentPage) ? (int)$currentPage : 1;
    }

    protected function resolveCurrentPage(): int
    {
        return (int)($_GET[$this->pageName] ?? 1);
    }

    protected function resolveCurrentPath(): string
    {
        return $_SERVER['REQUEST_URI'] ? strtok($_SERVER['REQUEST_URI'], '?') : '/';
    }

    protected function isValidPageNumber(int $page): bool
    {
        return $page >= 1 && filter_var($page, FILTER_VALIDATE_INT) !== false;
    }

    public function url(int $page): string
    {
        if ($page <= 0) {
            $page = 1;
        }

        $parameters = [$this->pageName => $page];

        if (count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
        }

        return $this->path . (count($parameters) ? '?' . http_build_query($parameters) : '') . $this->buildFragment();
    }

    public function appends(array|string $key, ?string $value = null): static
    {
        if (is_array($key)) {
            return $this->appendArray($key);
        }

        return $this->addQuery($key, $value);
    }

    public function fragment(?string $fragment): static
    {
        $this->fragment = $fragment;

        return $this;
    }

    public function nextPageUrl(): ?string
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage() + 1);
        }

        return null;
    }

    public function previousPageUrl(): ?string
    {
        if ($this->currentPage() > 1) {
            return $this->url($this->currentPage() - 1);
        }

        return null;
    }

    public function items(): Collection
    {
        return $this->items;
    }

    public function firstItem(): ?int
    {
        return $this->items->isEmpty() ? null : ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function lastItem(): ?int
    {
        return $this->items->isEmpty() ? null : $this->firstItem() + $this->items->count() - 1;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function hasPages(): bool
    {
        return $this->currentPage() != 1 || $this->hasMorePages();
    }

    public function hasMorePages(): bool
    {
        return $this->items->count() > $this->perPage;
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage() <= 1;
    }

    public function getIterator(): \ArrayIterator
    {
        return $this->items->getIterator();
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function isNotEmpty(): bool
    {
        return $this->items->isNotEmpty();
    }

    public function count(): int
    {
        return $this->items->count();
    }

    public function getCollection(): Collection
    {
        return $this->items;
    }

    public function setCollection(Collection $collection): static
    {
        $this->items = $collection;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getUrlRange(int $start, int $end): array
    {
        return collect(range($start, $end))->mapWithKeys(function ($page) {
            return [$page => $this->url($page)];
        })->all();
    }

    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    protected function appendArray(array $keys): static
    {
        foreach ($keys as $key => $value) {
            $this->addQuery($key, $value);
        }

        return $this;
    }

    protected function addQuery(string $key, string $value): static
    {
        if ($key !== $this->pageName) {
            $this->query[$key] = $value;
        }

        return $this;
    }

    protected function buildFragment(): string
    {
        return $this->fragment ? '#' . $this->fragment : '';
    }

    public static function make(array $items, int $perPage, ?int $currentPage = null, array $options = []): static
    {
        return new static($items, $perPage, $currentPage, $options);
    }
}
