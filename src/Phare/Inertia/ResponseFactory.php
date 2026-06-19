<?php

namespace Phare\Inertia;

class ResponseFactory
{
    protected array $sharedProps = [];

    /** @var string|\Closure|null */
    protected $version = null;

    protected string $rootView = 'app';

    /** @var callable|null */
    protected $viewRenderer = null;

    public function render(string $component, array $props = []): Response
    {
        $response = new Response(
            $component,
            $props,
            $this->rootView,
            $this->getVersion(),
        );

        // Shared props are resolved when the response is built, not now, so
        // props shared after this call (e.g. by middleware) are still included.
        $response->setSharedResolver(fn () => $this->sharedProps);

        if ($this->viewRenderer !== null) {
            $response->setViewRenderer($this->viewRenderer);
        }

        return $response;
    }

    public function setViewRenderer(callable $renderer): void
    {
        $this->viewRenderer = $renderer;
    }

    /**
     * Share a prop with every Inertia response. Deep-merges so sibling keys
     * are preserved across multiple share() calls for the same top-level key.
     *
     * @param string|array $key
     */
    public function share($key, mixed $value = null): void
    {
        if (is_array($key)) {
            $this->sharedProps = array_replace_recursive($this->sharedProps, $key);

            return;
        }

        if (is_array($value) && is_array($this->sharedProps[$key] ?? null)) {
            $this->sharedProps[$key] = array_replace_recursive($this->sharedProps[$key], $value);

            return;
        }

        $this->sharedProps[$key] = $value;
    }

    public function getShared(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->sharedProps;
        }

        return $this->sharedProps[$key] ?? null;
    }

    /**
     * @param string|\Closure|null $version
     */
    public function version($version): void
    {
        $this->version = $version;
    }

    public function getVersion(): ?string
    {
        $version = $this->version instanceof \Closure ? ($this->version)() : $this->version;

        return $version !== null ? (string)$version : null;
    }

    public function setRootView(string $rootView): void
    {
        $this->rootView = $rootView;
    }

    public function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    public function optional(callable $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    /**
     * Build the root element the `@inertia` Blade directive emits on a full
     * page load.
     *
     * Inertia v3 reads the initial page from a sibling
     * `<script type="application/json" data-page="app">` element (via its
     * textContent) and mounts the app into a bare `<div id="app">`. The page
     * JSON lives in script text, so only `<`, `>` and `&` need escaping
     * (JSON_HEX_TAG | JSON_HEX_AMP) to keep it from terminating the script.
     */
    public static function renderRootElement(array $page, string $id = 'app'): string
    {
        $json = json_encode(
            $page,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );

        return "<div id=\"{$id}\"></div>"
            . "<script type=\"application/json\" data-page=\"{$id}\">{$json}</script>";
    }
}
