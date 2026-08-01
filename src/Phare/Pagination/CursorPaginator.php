<?php

namespace Phare\Pagination;

use Phare\Collections\Collection;
use Phare\Contracts\Support\Arrayable;
use Phare\Contracts\Support\Jsonable;

class CursorPaginator implements \Countable, \IteratorAggregate, \JsonSerializable, Arrayable, Jsonable
{
    protected Collection $items;

    protected int $perPage;

    protected ?string $path = '/';

    protected array $query = [];

    protected ?string $fragment = null;

    protected string $cursorName = 'cursor';

    protected ?Cursor $cursor = null;

    protected array $parameters = [];

    protected array $options = [];

    protected bool $hasMore = false;

    public function __construct($items, int $perPage, ?Cursor $cursor = null, array $options = [])
    {
        $this->options = $options;

        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        $this->perPage = $perPage;
        $this->cursor = $cursor;
        $this->path = $this->path ?? Paginator::resolveCurrentPath();
        $this->path = $this->path !== '/' ? rtrim($this->path, '/') : $this->path;

        $this->setItems($items);
    }

    protected function setItems($items): void
    {
        $this->items = $items instanceof Collection ? $items : new Collection($items);
        $this->hasMore = $this->items->count() > $this->perPage;
        $this->items = $this->items->slice(0, $this->perPage)->values();

        if (!is_null($this->cursor) && $this->cursor->pointsToPreviousItems()) {
            $this->items = $this->items->reverse()->values();
        }
    }

    public function items(): array
    {
        return $this->items->toArray();
    }

    public function through(callable $callback): static
    {
        $this->items = $this->items->map($callback);

        return $this;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function hasMorePages(): bool
    {
        return (is_null($this->cursor) && $this->hasMore)
            || (!is_null($this->cursor) && $this->cursor->pointsToNextItems() && $this->hasMore)
            || (!is_null($this->cursor) && $this->cursor->pointsToPreviousItems());
    }

    public function hasPages(): bool
    {
        return !$this->onFirstPage() || $this->hasMorePages();
    }

    public function onFirstPage(): bool
    {
        return is_null($this->cursor) || ($this->cursor->pointsToPreviousItems() && !$this->hasMore);
    }

    public function onLastPage(): bool
    {
        return !$this->hasMorePages();
    }

    public function getIterator(): \ArrayIterator
    {
        return $this->items->getIterator();
    }

    public function count(): int
    {
        return $this->items->count();
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function appends(array|string|null $key, mixed $value = null): static
    {
        if (is_null($key)) {
            return $this;
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->addQuery($k, $v);
            }

            return $this;
        }

        return $this->addQuery($key, $value);
    }

    public function withQueryString(): static
    {
        $queryString = Paginator::resolveQueryString([]);

        if (is_array($queryString)) {
            return $this->appends($queryString);
        }

        parse_str((string)$queryString, $resolved);

        return $this->appends($resolved);
    }

    public function fragment(?string $fragment = null): static|string|null
    {
        if (is_null($fragment)) {
            return $this->fragment;
        }

        $this->fragment = $fragment;

        return $this;
    }

    public function url(?Cursor $cursor): string
    {
        $parameters = is_null($cursor) ? [] : [$this->cursorName => $cursor->encode()];

        if (count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
        }

        $separator = str_contains($this->path(), '?') ? '&' : '?';

        return $this->path() . $separator . http_build_query($parameters) . $this->buildFragment();
    }

    public function previousCursor(): ?Cursor
    {
        if (is_null($this->cursor) || ($this->cursor->pointsToPreviousItems() && !$this->hasMore)) {
            return null;
        }

        if ($this->items->isEmpty()) {
            return null;
        }

        return $this->getCursorForItem($this->items->first(), false);
    }

    public function nextCursor(): ?Cursor
    {
        if ((is_null($this->cursor) && !$this->hasMore)
            || (!is_null($this->cursor) && $this->cursor->pointsToNextItems() && !$this->hasMore)) {
            return null;
        }

        if ($this->items->isEmpty()) {
            return null;
        }

        return $this->getCursorForItem($this->items->last(), true);
    }

    public function previousPageUrl(): ?string
    {
        $cursor = $this->previousCursor();

        return $cursor ? $this->url($cursor) : null;
    }

    public function nextPageUrl(): ?string
    {
        $cursor = $this->nextCursor();

        return $cursor ? $this->url($cursor) : null;
    }

    public function getCursorForItem(mixed $item, bool $isNext = true): Cursor
    {
        return new Cursor($this->getParametersForItem($item), $isNext);
    }

    public function getParametersForItem(mixed $item): array
    {
        $parameters = [];

        foreach ($this->parameters as $parameterName) {
            if (is_array($item)) {
                $parameters[$parameterName] = $item[$parameterName] ?? null;

                continue;
            }

            if (is_object($item)) {
                $parameters[$parameterName] = $item->{$parameterName} ?? null;

                continue;
            }

            throw new \InvalidArgumentException('Only arrays and objects are supported when cursor paginating items.');
        }

        return $parameters;
    }

    protected function addQuery(string $key, mixed $value): static
    {
        if ($key !== $this->cursorName) {
            $this->query[$key] = $value;
        }

        return $this;
    }

    protected function buildFragment(): string
    {
        return $this->fragment ? '#' . $this->fragment : '';
    }

    public function toArray(): array
    {
        return [
            'data' => $this->items->toArray(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'next_cursor' => $this->nextCursor()?->encode(),
            'next_page_url' => $this->nextPageUrl(),
            'prev_cursor' => $this->previousCursor()?->encode(),
            'prev_page_url' => $this->previousPageUrl(),
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

    public function toPrettyJson(int $options = 0): string
    {
        return $this->toJson(JSON_PRETTY_PRINT | $options);
    }
}
