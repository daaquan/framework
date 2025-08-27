<?php

namespace Phare\View;

use Closure;
use Phare\Container\Container;

class View
{
    protected array $data = [];
    protected array $shared = [];
    protected ?string $view = null;
    public Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Add a piece of data to the view.
     */
    public function with(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Get a piece of data from the view.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $this->data[$key] ?? $default;
    }

    /**
     * Get all view data.
     */
    public function getData(): array
    {
        return array_merge($this->shared, $this->data);
    }

    /**
     * Check if the view has a piece of data.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data) || array_key_exists($key, $this->shared);
    }

    /**
     * Set the view name.
     */
    public function setView(string $view): static
    {
        $this->view = $view;
        return $this;
    }

    /**
     * Get the view name.
     */
    public function getView(): ?string
    {
        return $this->view;
    }

    /**
     * Share data with all views.
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Get shared data.
     */
    public function getShared(): array
    {
        return $this->shared;
    }

    /**
     * Render the view.
     */
    public function render(): string
    {
        if (!$this->view) {
            throw new \InvalidArgumentException('No view specified.');
        }

        // In a real implementation, this would render the view template
        // For now, return a simple representation
        return "View: {$this->view} with data: " . json_encode($this->getData());
    }

    /**
     * Convert the view to a string.
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Dynamically access view data.
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Dynamically set view data.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->with($key, $value);
    }

    /**
     * Check if view data exists.
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }
}