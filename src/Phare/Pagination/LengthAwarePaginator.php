<?php

namespace Phare\Pagination;

use Phare\Collections\Collection;

class LengthAwarePaginator extends Paginator
{
    protected int $total;
    protected int $lastPage;

    public function __construct($items, int $total, int $perPage, ?int $currentPage = null, array $options = [])
    {
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $this->setCurrentPage($currentPage);
        $this->lastPage = max((int) ceil($total / $perPage), 1);

        $this->items = $items instanceof Collection ? $items : new Collection($items);
        $this->options = $options;
        
        $this->path = $this->options['path'] ?? $this->resolveCurrentPath();
        $this->pageName = $this->options['pageName'] ?? 'page';
    }

    public function total(): int
    {
        return $this->total;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage() < $this->lastPage();
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function firstItem(): ?int
    {
        return $this->total > 0 ? ($this->currentPage - 1) * $this->perPage + 1 : null;
    }

    public function lastItem(): ?int
    {
        return $this->total > 0 ? min($this->total, $this->currentPage * $this->perPage) : null;
    }

    public function through(callable $callback): static
    {
        $this->items = $this->items->map($callback);
        return $this;
    }

    public function render(?string $view = null, array $data = []): string
    {
        return $this->defaultView($data);
    }

    public function simplePaginate(): string
    {
        return $this->simpleView();
    }

    public function links(?string $view = null, array $data = []): string
    {
        return $this->render($view, $data);
    }

    protected function defaultView(array $data = []): string
    {
        $links = [];

        // Previous Page Link
        if (!$this->onFirstPage()) {
            $links[] = '<a href="' . $this->previousPageUrl() . '">&laquo; Previous</a>';
        } else {
            $links[] = '<span>&laquo; Previous</span>';
        }

        // Pagination Elements
        foreach (range(1, $this->lastPage()) as $page) {
            if ($page == $this->currentPage()) {
                $links[] = '<strong>' . $page . '</strong>';
            } else {
                $links[] = '<a href="' . $this->url($page) . '">' . $page . '</a>';
            }
        }

        // Next Page Link  
        if ($this->hasMorePages()) {
            $links[] = '<a href="' . $this->nextPageUrl() . '">Next &raquo;</a>';
        } else {
            $links[] = '<span>Next &raquo;</span>';
        }

        return '<div class="pagination">' . implode(' | ', $links) . '</div>';
    }

    protected function simpleView(): string
    {
        $links = [];

        if (!$this->onFirstPage()) {
            $links[] = '<a href="' . $this->previousPageUrl() . '">&laquo; Previous</a>';
        }

        if ($this->hasMorePages()) {
            $links[] = '<a href="' . $this->nextPageUrl() . '">Next &raquo;</a>';
        }

        return '<div class="simple-pagination">' . implode(' | ', $links) . '</div>';
    }

    public function getUrlRange(int $start, int $end): array
    {
        $start = max($start, 1);
        $end = min($end, $this->lastPage());

        $urls = [];
        for ($page = $start; $page <= $end; $page++) {
            $urls[$page] = $this->url($page);
        }

        return $urls;
    }

    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage(),
            'last_page_url' => $this->url($this->lastPage()),
            'links' => $this->linkCollection()->toArray(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
            'total' => $this->total(),
        ];
    }

    protected function linkCollection(): Collection
    {
        $links = new Collection();

        // Previous
        $links->push([
            'url' => $this->previousPageUrl(),
            'label' => '&laquo; Previous',
            'active' => false,
        ]);

        // Pages
        foreach (range(1, $this->lastPage()) as $page) {
            $links->push([
                'url' => $page == $this->currentPage() ? null : $this->url($page),
                'label' => (string) $page,
                'active' => $page == $this->currentPage(),
            ]);
        }

        // Next
        $links->push([
            'url' => $this->nextPageUrl(),
            'label' => 'Next &raquo;',
            'active' => false,
        ]);

        return $links;
    }

    public static function make(array $items, int $total, int $perPage, ?int $currentPage = null, array $options = []): static
    {
        return new static($items, $total, $perPage, $currentPage, $options);
    }
}