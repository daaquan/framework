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

    protected bool $hasMore = false;

    protected static ?\Closure $currentPathResolver = null;

    protected static ?\Closure $currentPageResolver = null;

    protected static ?\Closure $queryStringResolver = null;

    public int $onEachSide = 3;

    public function __construct($items, int $perPage, ?int $currentPage = null, array $options = [])
    {
        $this->options = $options;

        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        $this->perPage = $perPage;
        $this->pageName = $this->pageName ?? 'page';
        $this->path = $this->path ?? static::resolveCurrentPath();
        $this->path = $this->path !== '/' ? rtrim($this->path, '/') : $this->path;
        $this->currentPage = $this->setCurrentPage($currentPage);

        $this->setItems($items);
    }

    protected function setCurrentPage(?int $currentPage): int
    {
        $currentPage = $currentPage ?: static::resolveCurrentPage($this->pageName ?? 'page');

        return $this->isValidPageNumber($currentPage) ? (int)$currentPage : 1;
    }

    protected function setItems($items): void
    {
        $this->items = $items instanceof Collection ? $items : new Collection($items);
        $this->hasMore = $this->items->count() > $this->perPage;
        $this->items = $this->items->slice(0, $this->perPage)->values();
    }

    public static function resolveCurrentPage(string $pageName = 'page', int $default = 1): int
    {
        if (isset(static::$currentPageResolver)) {
            return (int)call_user_func(static::$currentPageResolver, $pageName);
        }

        return (int)($_GET[$pageName] ?? $default);
    }

    public static function currentPageResolver(\Closure $resolver): void
    {
        static::$currentPageResolver = $resolver;
    }

    public static function resolveCurrentPath(string $default = '/'): string
    {
        if (isset(static::$currentPathResolver)) {
            return (string)call_user_func(static::$currentPathResolver);
        }

        return isset($_SERVER['REQUEST_URI']) ? (string)strtok($_SERVER['REQUEST_URI'], '?') : $default;
    }

    public static function currentPathResolver(\Closure $resolver): void
    {
        static::$currentPathResolver = $resolver;
    }

    public static function resolveQueryString(array|string|null $default = null): array|string|null
    {
        if (isset(static::$queryStringResolver)) {
            return call_user_func(static::$queryStringResolver);
        }

        return $default;
    }

    public static function queryStringResolver(\Closure $resolver): void
    {
        static::$queryStringResolver = $resolver;
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

        $separator = str_contains($this->path(), '?') ? '&' : '?';

        return $this->path() . $separator . http_build_query($parameters) . $this->buildFragment();
    }

    public function appends(array|string|null $key, mixed $value = null): static
    {
        if (is_null($key)) {
            return $this;
        }

        if (is_array($key)) {
            return $this->appendArray($key);
        }

        return $this->addQuery($key, $value);
    }

    public function fragment(?string $fragment = null): static|string|null
    {
        if (is_null($fragment)) {
            return $this->fragment;
        }

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
        return $this->hasMore;
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
            'current_page_url' => $this->url($this->currentPage()),
            'data' => $this->items->toArray(),
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path(),
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

    public function withQueryString(): static
    {
        $queryString = static::resolveQueryString([]);

        if (is_array($queryString)) {
            return $this->appends($queryString);
        }

        parse_str((string)$queryString, $resolved);

        return $this->appends($resolved);
    }

    protected function addQuery(string $key, mixed $value): static
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

    public function path(): ?string
    {
        return $this->path;
    }

    public function withPath(string $path): static
    {
        return $this->setPath($path);
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getPageName(): ?string
    {
        return $this->pageName;
    }

    public function setPageName(string $name): static
    {
        $this->pageName = $name;

        return $this;
    }

    public function onEachSide(int $count): static
    {
        $this->onEachSide = $count;

        return $this;
    }

    public static function make(array $items, int $perPage, ?int $currentPage = null, array $options = []): static
    {
        return new static($items, $perPage, $currentPage, $options);
    }
}
