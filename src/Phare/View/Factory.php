<?php

namespace Phare\View;

use Closure;
use Phare\Container\Container;

class Factory
{
    protected Container $container;
    protected array $composers = [];
    protected array $creators = [];
    protected array $shared = [];
    protected array $extensions = [];
    protected array $paths = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->paths = ['resources/views'];
    }

    /**
     * Create a new view instance.
     */
    public function make(string $view, array $data = [], array $mergeData = []): View
    {
        $path = $this->normalizeName($view);

        $viewInstance = new View($this->container);
        $viewInstance->setView($path);

        // Add shared data
        foreach ($this->shared as $key => $value) {
            $viewInstance->share($key, $value);
        }

        // Add view data (mergeData should override data)
        $viewInstance->with(array_merge($data, $mergeData));

        // Call view creators
        $this->callCreators($viewInstance);

        // Call view composers
        $this->callComposers($viewInstance);

        return $viewInstance;
    }

    /**
     * Determine if a given view exists.
     */
    public function exists(string $view): bool
    {
        $path = $this->normalizeName($view);
        
        foreach ($this->paths as $viewPath) {
            foreach ($this->extensions as $extension) {
                $fullPath = $viewPath . '/' . $path . $extension;
                if (file_exists($fullPath)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add a location to the array of view locations.
     */
    public function addLocation(string $location): void
    {
        $this->paths[] = $location;
    }

    /**
     * Add a new namespace to the loader.
     */
    public function addNamespace(string $namespace, string $hints): static
    {
        // In a real implementation, this would handle namespaced views
        return $this;
    }

    /**
     * Register a view composer event.
     */
    public function composer(string|array $views, string|Closure $callback): void
    {
        $views = (array) $views;

        foreach ($views as $view) {
            $view = $this->normalizeName($view);
            
            if (!isset($this->composers[$view])) {
                $this->composers[$view] = [];
            }
            
            $this->composers[$view][] = $callback;
        }
    }

    /**
     * Register a view creator event.
     */
    public function creator(string|array $views, string|Closure $callback): void
    {
        $views = (array) $views;

        foreach ($views as $view) {
            $view = $this->normalizeName($view);
            
            if (!isset($this->creators[$view])) {
                $this->creators[$view] = [];
            }
            
            $this->creators[$view][] = $callback;
        }
    }

    /**
     * Share a piece of data with all views.
     */
    public function share(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $innerKey => $innerValue) {
                $this->shared[$innerKey] = $innerValue;
            }
        } else {
            $this->shared[$key] = $value;
        }
    }

    /**
     * Register a valid view extension and its engine.
     */
    public function addExtension(string $extension, string $engine, ?Closure $resolver = null): void
    {
        $this->extensions[$extension] = $engine;
    }

    /**
     * Get the extension to engine bindings.
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Call the composer for a given view.
     */
    protected function callComposers(View $view): void
    {
        $viewName = $view->getView();
        
        if (!$viewName) {
            return;
        }

        // Call exact matches
        if (isset($this->composers[$viewName])) {
            foreach ($this->composers[$viewName] as $callback) {
                $this->callCallback($callback, $view);
            }
        }

        // Call wildcard matches (but skip exact matches we already called)
        foreach ($this->composers as $pattern => $callbacks) {
            if ($pattern !== $viewName && $this->matchesPattern($pattern, $viewName)) {
                foreach ($callbacks as $callback) {
                    $this->callCallback($callback, $view);
                }
            }
        }
    }

    /**
     * Call the creators for a given view.
     */
    protected function callCreators(View $view): void
    {
        $viewName = $view->getView();
        
        if (!$viewName) {
            return;
        }

        // Call exact matches
        if (isset($this->creators[$viewName])) {
            foreach ($this->creators[$viewName] as $callback) {
                $this->callCallback($callback, $view);
            }
        }

        // Call wildcard matches
        foreach ($this->creators as $pattern => $callbacks) {
            if ($this->matchesPattern($pattern, $viewName)) {
                foreach ($callbacks as $callback) {
                    $this->callCallback($callback, $view);
                }
            }
        }
    }

    /**
     * Call a composer callback.
     */
    protected function callCallback(string|Closure $callback, View $view): void
    {
        if ($callback instanceof Closure) {
            $callback($view);
        } elseif (is_string($callback)) {
            if (class_exists($callback)) {
                $composer = $this->container->make($callback);
                
                if (method_exists($composer, 'compose')) {
                    $composer->compose($view);
                } elseif (is_callable($composer)) {
                    $composer($view);
                }
            }
        }
    }

    /**
     * Check if a pattern matches a view name.
     */
    protected function matchesPattern(string $pattern, string $viewName): bool
    {
        if (strpos($pattern, '*') === false) {
            return $pattern === $viewName;
        }

        // Convert pattern to regex - escape special chars except *
        $regex = preg_quote($pattern, '/');
        $regex = str_replace('\\*', '.*', $regex);
        return (bool) preg_match('/^' . $regex . '$/i', $viewName);
    }

    /**
     * Normalize a view name.
     */
    protected function normalizeName(string $name): string
    {
        return str_replace('.', '/', $name);
    }

    /**
     * Get all of the shared data for the environment.
     */
    public function getShared(): array
    {
        return $this->shared;
    }

    /**
     * Get the view paths.
     */
    public function getPaths(): array
    {
        return $this->paths;
    }
}