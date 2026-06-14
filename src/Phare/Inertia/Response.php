<?php

namespace Phare\Inertia;

use Phalcon\Http\RequestInterface;
use Phare\Http\Response as HttpResponse;

class Response
{
    /** @var callable|null */
    protected $viewRenderer = null;

    /** @var callable|null */
    protected $sharedResolver = null;

    public function __construct(
        protected string $component,
        protected array $props = [],
        protected string $rootView = 'app',
        protected ?string $version = null,
    ) {}

    public function setSharedResolver(callable $resolver): static
    {
        $this->sharedResolver = $resolver;

        return $this;
    }

    public function component(): string
    {
        return $this->component;
    }

    public function props(): array
    {
        return $this->props;
    }

    public function setViewRenderer(callable $renderer): static
    {
        $this->viewRenderer = $renderer;

        return $this;
    }

    public function toResponse(RequestInterface $request): HttpResponse
    {
        $page = [
            'component' => $this->component,
            'props' => $this->resolveProps($request),
            'url' => $this->resolveUrl($request),
            'version' => $this->version,
        ];

        $http = new HttpResponse();

        if ($this->isInertiaRequest($request)) {
            return $http->json($page, 200, [
                'X-Inertia' => 'true',
                'Vary' => 'X-Inertia',
            ]);
        }

        $http->setContent($this->renderRootView($page));

        return $http;
    }

    /**
     * Decide which props to send, then evaluate any closures / lazy markers.
     */
    protected function resolveProps(RequestInterface $request): array
    {
        // Merge shared props (resolved now, so anything shared after render()
        // — e.g. by middleware — is included) under the per-page props.
        $shared = $this->sharedResolver !== null ? ($this->sharedResolver)() : [];
        $props = array_replace_recursive($shared, $this->props);

        if ($this->isPartial($request)) {
            $only = array_filter(explode(',', (string)$request->getHeader('X-Inertia-Partial-Data')));
            $props = array_intersect_key($props, array_flip($only));
        } else {
            // Lazy/optional props are excluded from a full visit.
            $props = array_filter($props, fn ($value) => !$value instanceof LazyProp);
        }

        return array_map(fn ($value) => $this->evaluate($value), $props);
    }

    protected function evaluate(mixed $value): mixed
    {
        if ($value instanceof LazyProp) {
            return $value();
        }

        if ($value instanceof \Closure) {
            return $value();
        }

        return $value;
    }

    protected function isInertiaRequest(RequestInterface $request): bool
    {
        return (bool)$request->getHeader('X-Inertia');
    }

    protected function isPartial(RequestInterface $request): bool
    {
        return $this->isInertiaRequest($request)
            && $request->getHeader('X-Inertia-Partial-Component') === $this->component
            && $request->getHeader('X-Inertia-Partial-Data') !== '';
    }

    protected function resolveUrl(RequestInterface $request): string
    {
        return method_exists($request, 'fullUrl')
            ? $request->fullUrl()
            : ($_SERVER['REQUEST_URI'] ?? '/');
    }

    protected function renderRootView(array $page): string
    {
        if ($this->viewRenderer !== null) {
            return ($this->viewRenderer)($this->rootView, ['page' => $page]);
        }

        return ResponseFactory::renderRootElement($page);
    }
}
