<?php

use Phalcon\Http\RequestInterface;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;
use Phare\Foundation\Http\Kernel;
use Phare\Foundation\Web;

class KernelOrchestrationDummyController
{
    public function show(int $id): array
    {
        return ['id' => $id];
    }
}

class KernelTestRoute
{
    public ?string $method = null;

    public string $name = '';

    public function via(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}

class KernelTestRouter
{
    /** @var array<int, array{path: string, definition: array<string, string>, route: KernelTestRoute}> */
    public array $adds = [];

    public function add(string $path, array $definition): KernelTestRoute
    {
        $route = new KernelTestRoute();
        $this->adds[] = [
            'path' => $path,
            'definition' => $definition,
            'route' => $route,
        ];

        return $route;
    }
}

class KernelTestEventsManager
{
    /** @var array<string, callable> */
    public array $handlers = [];

    public function attach(string $eventName, callable $handler): void
    {
        $this->handlers[$eventName] = $handler;
    }
}

class KernelTestRequest
{
    public function __construct(
        private string $uri,
        private string $method
    ) {}

    public function getURI(bool $local = false): string
    {
        return $this->uri;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}

class KernelTestWebApp extends Web
{
    /** @var array<int, string> */
    public array $appliedMiddlewares = [];

    public function middleware($abstract)
    {
        $this->appliedMiddlewares[] = $abstract;
    }

    public function mount($group)
    {
        // no-op for orchestration tests
    }
}

class KernelOrchestrationTestKernel extends Kernel
{
    protected array $middlewares = ['global.middleware'];

    protected array $middlewareGroups = [
        'web' => ['web.middleware'],
        'api' => [],
    ];

    protected array $routeMiddleware = [
        'auth' => 'route.auth.middleware',
    ];

    protected array $bootstrappers = [];

    public function handle(RequestInterface $request): ResponseInterface
    {
        return new Response();
    }
}

function kernelTestDeleteTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $target = $item->getPathname();
        $item->isDir() ? rmdir($target) : unlink($target);
    }

    rmdir($path);
}

it('orchestrates web route registration, params binding, and middleware order', function () {
    $basePath = sys_get_temp_dir() . '/phare-kernel-orchestration-' . bin2hex(random_bytes(6));
    mkdir($basePath . '/bootstrap/cache', 0777, true);

    $routesPath = $basePath . '/bootstrap/cache/routes.php';
    file_put_contents($routesPath, '<?php return ' . var_export([
        '/users/{id}' => [
            'GET' => [
                'path' => '/users/{id}',
                'namespace' => '',
                'controller' => 'KernelOrchestrationDummy',
                'action' => 'show',
                'method' => 'GET',
                'name' => 'users.show',
                'middleware' => ['auth'],
                'params' => ['int'],
            ],
        ],
    ], true) . ';');

    try {
        $app = new KernelTestWebApp($basePath);
        $router = new KernelTestRouter();
        $events = new KernelTestEventsManager();
        $request = new KernelTestRequest('/users/42', 'GET');

        $app->singleton('router', fn (array $parameters = []) => $router);
        $app->singleton('eventsManager', fn (array $parameters = []) => $events);
        $app->singleton('request', fn (array $parameters = []) => $request);

        new KernelOrchestrationTestKernel($app);

        expect($app->hasBeenBootstrapped())->toBeTrue();
        expect($app->make('routeParams'))->toBe(['id' => '42']);

        // 1 from hydration + 1 from selected route registration
        expect($router->adds)->toHaveCount(2);
        expect($router->adds[0]['path'])->toBe('/users/{id}');
        expect($router->adds[1]['path'])->toBe('/users/{id}');

        expect(isset($events->handlers['dispatch:beforeExecuteRoute']))->toBeTrue();

        // web group -> route -> global (syncMiddleware runs after registerRoutes)
        expect($app->appliedMiddlewares)->toBe([
            'web.middleware',
            'route.auth.middleware',
            'global.middleware',
        ]);
    } finally {
        kernelTestDeleteTree($basePath);
    }
});
