<?php

namespace Phare\Pagination;

class UrlWindow
{
    protected LengthAwarePaginator $paginator;

    public function __construct(LengthAwarePaginator $paginator)
    {
        $this->paginator = $paginator;
    }

    public static function make(LengthAwarePaginator $paginator): array
    {
        return (new static($paginator))->get();
    }

    public function get(): array
    {
        $onEachSide = $this->paginator->onEachSide;

        if ($this->paginator->lastPage() < ($onEachSide * 2) + 8) {
            return $this->getSmallSlider();
        }

        return $this->getUrlSlider($onEachSide);
    }

    protected function getSmallSlider(): array
    {
        return [
            'first' => $this->paginator->getUrlRange(1, $this->lastPage()),
            'slider' => null,
            'last' => null,
        ];
    }

    protected function getUrlSlider(int $onEachSide): array
    {
        $window = $onEachSide + 4;

        if (!$this->hasPages()) {
            return ['first' => null, 'slider' => null, 'last' => null];
        }

        if ($this->currentPage() <= $window) {
            return $this->getSliderTooCloseToBeginning($window, $onEachSide);
        }

        if ($this->currentPage() > ($this->lastPage() - $window)) {
            return $this->getSliderTooCloseToEnding($window, $onEachSide);
        }

        return $this->getFullSlider($onEachSide);
    }

    protected function getSliderTooCloseToBeginning(int $window, int $onEachSide): array
    {
        return [
            'first' => $this->paginator->getUrlRange(1, $window + $onEachSide),
            'slider' => null,
            'last' => $this->getFinish(),
        ];
    }

    protected function getSliderTooCloseToEnding(int $window, int $onEachSide): array
    {
        $last = $this->paginator->getUrlRange(
            $this->lastPage() - ($window + ($onEachSide - 1)),
            $this->lastPage()
        );

        return [
            'first' => $this->getStart(),
            'slider' => null,
            'last' => $last,
        ];
    }

    protected function getFullSlider(int $onEachSide): array
    {
        return [
            'first' => $this->getStart(),
            'slider' => $this->getAdjacentUrlRange($onEachSide),
            'last' => $this->getFinish(),
        ];
    }

    public function getAdjacentUrlRange(int $onEachSide): array
    {
        return $this->paginator->getUrlRange(
            $this->currentPage() - $onEachSide,
            $this->currentPage() + $onEachSide
        );
    }

    public function getStart(): array
    {
        return $this->paginator->getUrlRange(1, 2);
    }

    public function getFinish(): array
    {
        return $this->paginator->getUrlRange(
            $this->lastPage() - 1,
            $this->lastPage()
        );
    }

    public function hasPages(): bool
    {
        return $this->paginator->lastPage() > 1;
    }

    protected function currentPage(): int
    {
        return $this->paginator->currentPage();
    }

    protected function lastPage(): int
    {
        return $this->paginator->lastPage();
    }
}
