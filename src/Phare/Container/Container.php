<?php

namespace Phare\Container;

use Closure;
use Phalcon\Annotations\Annotation;
use Phalcon\Assets\Manager;
use Phalcon\Config\ConfigInterface;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;
use Phalcon\Di\Exception;
use Phalcon\Encryption\Crypt\CryptInterface;
use Phalcon\Encryption\Security;
use Phalcon\Filter\Filter;
use Phalcon\Flash\Direct;
use Phalcon\Flash\Session;
use Phalcon\Html\Escaper\EscaperInterface;
use Phalcon\Html\TagFactory;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\Response\CookiesInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\DispatcherInterface;
use Phalcon\Mvc\Model\MetadataInterface;
use Phalcon\Mvc\RouterInterface;
use Phalcon\Mvc\Url\UrlInterface;
use Phalcon\Mvc\ViewInterface;
use Phalcon\Session\BagInterface;
use Phalcon\Session\ManagerInterface;
use Phalcon\Translate\Adapter\AbstractAdapter;
use Phare\Container\Exceptions\ContainerException;
use Phare\Container\Exceptions\ServiceNotFoundException;
use Phare\Contracts\Container\ContextualAttribute as ContextualAttributeContract;
use Phare\Contracts\Foundation\Container as ContractsContainer;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use TypeError;

class Container extends Di implements ContractsContainer, PsrContainerInterface
{
    /**
     * Phalcon standard services
     *
     * @var string[]
     */
    protected array $reservedServices = [
        'config' => ConfigInterface::class,
        'dispatcher' => DispatcherInterface::class,
        'router' => RouterInterface::class,
        'url' => UrlInterface::class,
        'request' => RequestInterface::class,
        'response' => ResponseInterface::class,
        'cookies' => CookiesInterface::class,
        'filter' => Filter::class,
        'flashDirect' => Direct::class,
        'flashSession' => Session::class,
        'session' => ManagerInterface::class,
        'eventsManager' => \Phalcon\Events\ManagerInterface::class,
        'pdo' => AdapterInterface::class,
        'security' => Security::class,
        'encrypter' => CryptInterface::class,
        'tag' => TagFactory::class,
        'escaper' => EscaperInterface::class,
        'annotations' => Annotation::class,
        'modelsManager' => \Phalcon\Mvc\Model\ManagerInterface::class,
        'modelsMetadata' => MetadataInterface::class,
        'modelTransaction' => \Phalcon\Mvc\Model\Transaction\ManagerInterface::class,
        'assets' => Manager::class,
        'di' => DiInterface::class,
        'sessionBag' => BagInterface::class,
        'view' => ViewInterface::class,
        'translator' => AbstractAdapter::class,
    ];

    /**
     * Phalcon standard services
     *
     * @var string[]
     */
    protected array $reservedServiceAlias = [
        ConfigInterface::class => 'config',
        DispatcherInterface::class => 'dispatcher',
        RouterInterface::class => 'router',
        UrlInterface::class => 'url',
        RequestInterface::class => 'request',
        ResponseInterface::class => 'response',
        CookiesInterface::class => 'cookies',
        Filter::class => 'filter',
        Direct::class => 'flashDirect',
        Session::class => 'flashSession',
        ManagerInterface::class => 'session',
        \Phalcon\Events\ManagerInterface::class => 'eventsManager',
        AdapterInterface::class => 'pdo',
        Security::class => 'security',
        CryptInterface::class => 'encrypter',
        TagFactory::class => 'tag',
        EscaperInterface::class => 'escaper',
        Annotation::class => 'annotations',
        \Phalcon\Mvc\Model\ManagerInterface::class => 'modelsManager',
        MetadataInterface::class => 'modelsMetadata',
        \Phalcon\Mvc\Model\Transaction\ManagerInterface::class => 'modelTransaction',
        Manager::class => 'assets',
        DiInterface::class => 'di',
        BagInterface::class => 'sessionBag',
        ViewInterface::class => 'view',
        AbstractAdapter::class => 'translator',
    ];

    /**
     * Map of alias => abstract. Inherited from Phalcon\Di\Di untyped, so we cannot add a type here.
     *
     * @var array<string, string>
     */
    protected $aliases = [];

    protected array $bindings = [
        'concrete' => [],
        'shared' => [],
    ];

    protected array $resolved = [];

    /**
     * Locally cached singleton instances. Populated by resolveInstance() so
     * repeat make() calls return the same instance even when Phalcon's DI
     * cache cannot represent the value (e.g. closure singletons returning
     * arrays or scalars).
     *
     * @var array<string, mixed>
     */
    protected array $resolvedInstances = [];

    /**
     * Current concrete build stack.
     *
     * @var array<int, string>
     */
    protected array $buildStack = [];

    /**
     * Contextual bindings map: [concrete => [abstract => implementation]]
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $contextual = [];

    /**
     * Tags map: [tag => [abstract...]]
     *
     * @var array<string, array<int, string>>
     */
    protected array $tags = [];

    /**
     * Callbacks fired for every resolved instance.
     *
     * @var array<int, Closure>
     */
    protected array $globalResolvingCallbacks = [];

    /**
     * Callbacks fired when a specific abstract is resolved.
     *
     * @var array<string, array<int, Closure>>
     */
    protected array $resolvingCallbacks = [];

    /**
     * Callbacks fired after resolving a specific abstract.
     *
     * @var array<string, array<int, Closure>>
     */
    protected array $afterResolvingCallbacks = [];

    /**
     * Callbacks fired when an abstract is rebound.
     *
     * @var array<string, array<int, Closure>>
     */
    protected array $reboundCallbacks = [];

    /**
     * Callbacks indexed by contextual attribute class name.
     *
     * @var array<class-string, array<int, Closure>>
     */
    protected array $afterResolvingAttributeCallbacks = [];

    /**
     * Decorators registered via extend() keyed by abstract.  Applied in
     * registration order whenever the abstract is freshly resolved.
     *
     * @var array<string, array<int, Closure>>
     */
    protected array $extenders = [];

    /**
     * Callbacks fired before any abstract is resolved.
     *
     * @var array<int, Closure>
     */
    protected array $globalBeforeResolvingCallbacks = [];

    /**
     * Callbacks fired before a specific abstract is resolved.
     *
     * @var array<string, array<int, Closure>>
     */
    protected array $beforeResolvingCallbacks = [];

    /**
     * Attribute resolution handlers registered via whenHasAttribute().
     *
     * @var array<class-string, Closure>
     */
    protected array $attributeHandlers = [];

    /**
     * Abstracts registered as scoped (per-request) singletons. Cleared by
     * {@see forgetScopedInstances()}.
     *
     * @var array<int, string>
     */
    protected array $scopedInstances = [];

    /**
     * Inner Phalcon service-store backend.
     */
    protected DiInterface $phalconDi;

    public function __construct()
    {
        parent::__construct();
        $this->phalconDi = $this;
    }

    /**
     * Inner Phalcon service-store backend accessor.
     */
    public function phalconDi(): DiInterface
    {
        return $this->phalconDi;
    }

    /**
     * Alias a type to a shortened name.
     */
    public function alias(string $abstract, string $alias): void
    {
        if ($alias === $abstract) {
            throw new \LogicException("[{$abstract}] is aliased to itself.");
        }
        $this->aliases[$alias] = $abstract;
    }

    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bindIf($abstract, $concrete, true);
    }

    public function singletonIf(string $abstract, $concrete = null): void
    {
        if (!$this->bound($abstract)) {
            $this->singleton($abstract, $concrete);
        }
    }

    /**
     * Register an already-built instance as a shared binding.
     *
     * Mirrors Laravel's `instance()`: the value bypasses container build
     * machinery and is returned directly from {@see make()}. When an
     * existing binding is replaced, rebinding callbacks fire so refresh
     * targets receive the new instance.
     */
    public function instance(string $abstract, mixed $instance): mixed
    {
        $abstract = $this->getAlias($abstract);

        $isRebind = $this->bound($abstract) || $this->resolved($abstract);

        $this->bindings['concrete'][$abstract] = $instance;
        $this->bindings['shared'][$abstract] = true;
        $this->resolved[$abstract] = true;
        $this->resolvedInstances[$abstract] = $instance;

        if ($isRebind) {
            $this->fireReboundCallbacks($abstract);
        }

        return $instance;
    }

    /**
     * Register a decorator that wraps the resolved binding.
     *
     * Mirrors Laravel's `extend()`: decorators fire in registration
     * order on each fresh resolution. When the binding is already
     * resolved, the new extender is applied immediately and the
     * cached singleton instance is replaced (rebinding callbacks
     * fire so consumers update).
     */
    public function extend(string $abstract, Closure $closure): void
    {
        $abstract = $this->getAlias($abstract);
        $this->extenders[$abstract][] = $closure;

        if ($this->resolved($abstract)) {
            $existing = $this->resolvedInstances[$abstract] ?? $this->make($abstract);
            $decorated = $closure($existing, $this);
            $this->resolvedInstances[$abstract] = $decorated;
            $this->fireReboundCallbacks($abstract);
        }
    }

    /**
     * Apply every registered extender for an abstract to the given instance.
     */
    protected function applyExtenders(string $abstract, mixed $instance): mixed
    {
        $abstract = $this->getAlias($abstract);
        foreach ($this->extenders[$abstract] ?? [] as $extender) {
            $instance = $extender($instance, $this);
        }

        return $instance;
    }

    /**
     * Forget a single shared resolution so the next make() rebuilds.
     */
    public function forgetInstance(string $abstract): void
    {
        $abstract = $this->getAlias($abstract);
        unset($this->resolved[$abstract], $this->resolvedInstances[$abstract]);

        try {
            // Phalcon Di stores shared instances internally; clearing the
            // service entry forces re-resolution. Use remove() guarded by
            // has() because Phalcon throws when the service is unknown.
            if ($this->has($abstract)) {
                $this->remove($abstract);
            }
        } catch (\Throwable) {
            // best-effort cleanup
        }
    }

    /**
     * Forget every cached shared resolution.
     */
    public function forgetInstances(): void
    {
        foreach (array_keys($this->resolvedInstances) as $abstract) {
            $this->forgetInstance($abstract);
        }
        $this->resolvedInstances = [];
        $this->resolved = [];
    }

    /**
     * Forget extenders registered for the given abstract.
     */
    public function forgetExtenders(string $abstract): void
    {
        unset($this->extenders[$this->getAlias($abstract)]);
    }

    /**
     * Method bindings keyed by `Class@method` for {@see call()}.
     *
     * @var array<string, Closure>
     */
    protected array $methodBindings = [];

    public function bindMethod(string $method, Closure $callback): void
    {
        $this->methodBindings[$method] = $callback;
    }

    public function hasMethodBinding(string $method): bool
    {
        return isset($this->methodBindings[$method]);
    }

    public function callMethodBinding(string $method, mixed $instance, array $parameters = []): mixed
    {
        return ($this->methodBindings[$method])($instance, $parameters);
    }

    /**
     * Invoke a callable with autowired dependencies and named overrides.
     * Accepts Closure, `[$obj, 'method']`, or `'Class@method'` strings.
     */
    public function call($callback, array $parameters = [], ?string $defaultMethod = null): mixed
    {
        return BoundMethod::call($this, $callback, $parameters, $defaultMethod);
    }

    /**
     * Return a Closure that defers {@see call()} on the given callback.
     */
    public function wrap(Closure $callback, array $parameters = []): Closure
    {
        return fn () => $this->call($callback, $parameters);
    }

    /**
     * Return a Closure that resolves the abstract on each invocation.
     */
    public function factory(string $abstract): Closure
    {
        return fn () => $this->make($abstract);
    }

    /**
     * Register a beforeResolving callback. When $abstract is a Closure
     * (and $callback null), the callback is registered globally.
     */
    public function beforeResolving($abstract, ?Closure $callback = null): void
    {
        if ($abstract instanceof Closure) {
            $this->globalBeforeResolvingCallbacks[] = $abstract;

            return;
        }

        $this->beforeResolvingCallbacks[$this->getAlias($abstract)][] = $callback;
    }

    /**
     * Fire all beforeResolving callbacks (global + abstract-specific).
     */
    protected function fireBeforeResolvingCallbacks(string $abstract, array $parameters = []): void
    {
        foreach ($this->globalBeforeResolvingCallbacks as $cb) {
            $cb($abstract, $parameters, $this);
        }

        foreach ($this->beforeResolvingCallbacks[$abstract] ?? [] as $cb) {
            $cb($abstract, $parameters, $this);
        }
    }

    /**
     * Return the current build stack (abstracts being actively resolved).
     *
     * @return array<int, string>
     */
    public function currentlyResolving(): array
    {
        return $this->buildStack;
    }

    /**
     * Refresh a binding on a target: every time $abstract rebinds, call
     * $target->{$method}($newInstance). Returns the current resolved instance.
     */
    public function refresh(string $abstract, mixed $target, string $method): mixed
    {
        $instance = $this->make($abstract);

        $this->rebinding($abstract, function ($app, $new) use ($target, $method) {
            $target->$method($new);
        });

        return $instance;
    }

    /**
     * Register a scoped (per-request) singleton.  Behaves like singleton()
     * except {@see forgetScopedInstances()} can sweep these on request end.
     */
    public function scoped(string $abstract, $concrete = null): void
    {
        $this->scopedInstances[] = $this->getAlias($abstract);
        $this->singleton($abstract, $concrete);
    }

    public function scopedIf(string $abstract, $concrete = null): void
    {
        if (!$this->bound($abstract)) {
            $this->scoped($abstract, $concrete);
        }
    }

    /**
     * Forget every scoped resolution; non-scoped singletons survive.
     */
    public function forgetScopedInstances(): void
    {
        foreach ($this->scopedInstances as $abstract) {
            $this->forgetInstance($abstract);
        }
    }

    /**
     * Reset every internal map so the container behaves like a fresh instance.
     * Mirrors Laravel's `Container::flush()`.
     */
    public function flush(): void
    {
        $this->aliases = [];
        $this->resolved = [];
        $this->resolvedInstances = [];
        $this->bindings = ['concrete' => [], 'shared' => []];
        $this->extenders = [];
        $this->buildStack = [];
        $this->contextual = [];
        $this->tags = [];
        $this->globalResolvingCallbacks = [];
        $this->resolvingCallbacks = [];
        $this->afterResolvingCallbacks = [];
        $this->reboundCallbacks = [];
        $this->afterResolvingAttributeCallbacks = [];

        try {
            $this->reset();
        } catch (\Throwable) {
            // Phalcon Di::reset may not exist on every version; best-effort.
        }
    }

    /**
     * Register a binding with the container.
     *
     * @throws TypeError
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        $isRebind = $this->bound($abstract) || $this->resolved($abstract);

        if ($isRebind) {
            $this->remove($abstract);
            unset($this->resolved[$abstract], $this->resolvedInstances[$abstract]);
        }

        if ($shared) {
            $this->bindings['shared'][$abstract] = true;
        }

        // Class-string concretes go to Phalcon as a closure so that getShared/get
        // routes through Container::resolve() for autowiring instead of `new $class()`.
        // skipAliasReserved=true prevents resolve($concrete) from redirecting back to
        // make($abstract) when $concrete is itself in reservedServiceAlias.
        // Closure concretes are wrapped so the container ($app) is auto-passed
        // when the closure declares a parameter — mirroring Laravel's `function ($app) {}` convention.
        $definition = $concrete;
        if (is_string($concrete)) {
            $self = $this;
            $definition = function ($params = []) use ($concrete, $self) {
                return $self->resolve($concrete, is_array($params) ? $params : [], true);
            };
        } elseif ($concrete instanceof Closure) {
            $self = $this;
            $original = $concrete;
            $definition = function ($params = []) use ($original, $self) {
                $reflection = new \ReflectionFunction($original);
                $declared = $reflection->getParameters();
                if ($declared === []) {
                    return $original();
                }

                // If the first declared param expects an `array`, route Phalcon's $params through unchanged.
                // Otherwise prepend the container so providers can use `function ($app)` / `function (Container $app)`.
                $firstType = $declared[0]->getType();
                $firstTypeName = $firstType instanceof \ReflectionNamedType ? $firstType->getName() : null;
                if ($firstTypeName === 'array') {
                    $args = is_array($params) ? [$params] : [(array)$params];
                } else {
                    $args = is_array($params) ? $params : [$params];
                    array_unshift($args, $self);
                }

                return $original(...$args);
            };
        }

        $this->set($abstract, $definition, $shared);
        $this->bindings['concrete'][$abstract] = $concrete;

        if ($isRebind) {
            $this->fireReboundCallbacks($abstract);
        }
    }

    public function bindIf(string $abstract, $concrete = null, bool $shared = false): void
    {
        if (!$this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    public function bound(string $abstract): bool
    {
        return isset($this->bindings['concrete'][$abstract]);
    }

    /**
     * Resolve the given type from the container.
     */
    public function make(string $abstract, array $parameters = [])
    {
        $abstract = $this->getAlias($abstract);

        $this->fireBeforeResolvingCallbacks($abstract, $parameters);

        return $this->doMake($abstract, $parameters);
    }

    /**
     * Internal make() body retained as a seam for beforeResolving hooks.
     */
    protected function doMake(string $abstract, array $parameters)
    {
        if ($this->resolved($abstract)) {
            $shared = $this->isShared($abstract);

            // Locally cached singleton instances bypass Phalcon DI entirely. This
            // matters for closure singletons that returned a non-object (array,
            // scalar) — Phalcon's getShared() would treat the stored value as a
            // service definition and fail with "Missing 'className' parameter".
            if ($shared && array_key_exists($abstract, $this->resolvedInstances)) {
                return $this->resolvedInstances[$abstract];
            }

            $getter = $shared ? 'getShared' : 'get';

            $instance = $this->phalconDi->$getter($abstract, $parameters);

            // Shared singletons return the cached instance — Laravel parity skips
            // resolving callbacks here so they fire once on the initial build.
            if (!$shared) {
                $this->fireResolvingCallbacks($abstract, $instance);
            }

            return $instance;
        }

        // For shared services registered via Phalcon DI, use getShared to maintain singleton behavior.
        // However, if the registered concrete is a Closure we must use our own resolve() path which
        // correctly passes $this (the container) as the first argument.  Phalcon's getShared() invokes
        // closures with zero arguments, causing an ArgumentCountError for any closure that expects $app.
        if ($this->isShared($abstract) || $this->isReserved($abstract)) {
            // Skip the Phalcon path when the concrete is a closure — resolve() handles it properly.
            $registeredConcrete = $this->bindings['concrete'][$abstract] ?? null;
            if ($registeredConcrete instanceof Closure) {
                // Fall through to our own resolve() call below.
            } else {
                try {
                    $service = $this->phalconDi->getService($abstract);
                    if ($service->isShared()) {
                        $instance = $this->phalconDi->getShared($abstract, $parameters);
                        $instance = $this->applyExtenders($abstract, $instance);
                        $this->resolved[$abstract] = true;
                        $this->resolvedInstances[$abstract] = $instance;
                        if (is_object($instance)) {
                            $instanceClass = get_class($instance);
                            if ($instanceClass !== $abstract) {
                                $this->aliases[$instanceClass] = $abstract;
                            }
                        }

                        $this->fireAfterResolvingClassAttributes($instance);

                        return $instance;
                    }
                } catch (Exception $e) {
                    // Service not found in Phalcon DI, continue to resolve
                }
            }
        }

        $instance = $this->resolve($abstract, $parameters);
        $instance = $this->applyExtenders($abstract, $instance);

        if ($this->isShared($abstract)) {
            $this->resolvedInstances[$abstract] = $instance;
        }

        if (is_object($instance)) {
            $instanceClass = get_class($instance);
            if ($instanceClass !== $abstract) {
                $this->aliases[$instanceClass] = $abstract;
            }
        }

        $this->fireResolvingCallbacks($abstract, $instance);
        $this->fireAfterResolvingClassAttributes($instance);

        return $instance;
    }

    /**
     * Fire after-resolving attribute callbacks for the resolved object's class-level attributes.
     */
    protected function fireAfterResolvingClassAttributes(mixed $instance): void
    {
        if (!is_object($instance)) {
            return;
        }

        $reflection = new \ReflectionClass($instance);
        $this->fireAfterResolvingAttributeCallbacks($reflection->getAttributes(), $instance);
    }

    public function resolved(string $abstract): bool
    {
        return $this->resolved[$abstract] ?? false;
    }

    /**
     * Register a resolving callback.
     *
     * @param string|Closure $abstract
     */
    public function resolving($abstract, ?Closure $callback = null): void
    {
        if ($abstract instanceof Closure && $callback === null) {
            $this->globalResolvingCallbacks[] = $abstract;

            return;
        }

        if (!is_string($abstract) || $callback === null) {
            throw new \InvalidArgumentException('Resolving callback requires an abstract and closure.');
        }

        $this->resolvingCallbacks[$abstract][] = $callback;
    }

    /**
     * Register an after resolving callback.
     *
     * @param string|Closure $abstract
     */
    public function afterResolving($abstract, ?Closure $callback = null): void
    {
        if ($abstract instanceof Closure && $callback === null) {
            $this->afterResolvingCallbacks['*'][] = $abstract;

            return;
        }

        if (!is_string($abstract) || $callback === null) {
            throw new \InvalidArgumentException('After resolving callback requires an abstract and closure.');
        }

        $this->afterResolvingCallbacks[$abstract][] = $callback;

        if ($this->resolved($abstract)) {
            $getter = $this->isShared($abstract) ? 'getShared' : 'get';
            $callback($this->$getter($abstract), $this);
        }
    }

    /**
     * Register a callback to fire after a contextual attribute resolves.
     */
    public function afterResolvingAttribute(string $attribute, Closure $callback): void
    {
        $this->afterResolvingAttributeCallbacks[$attribute][] = $callback;
    }

    /**
     * Register a rebinding callback.
     *
     * Mirrors Laravel: the callback fires on subsequent rebinds, not on registration.
     * If the abstract is already bound, eagerly resolve it to surface side effects.
     */
    public function rebinding(string $abstract, Closure $callback): void
    {
        $this->reboundCallbacks[$abstract][] = $callback;

        if ($this->bound($abstract)) {
            $this->make($abstract);
        }
    }

    /**
     * Resolve the abstract alias chain.
     */
    public function getAlias(string $abstract): string
    {
        while (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        return $abstract;
    }

    public function isReserved(string $abstract)
    {
        return $this->reservedServices[$abstract] ?? false;
    }

    public function isAliasReserved(string $abstract)
    {
        return $this->reservedServiceAlias[$abstract] ?? false;
    }

    public function isShared(string $abstract): bool
    {
        return $this->bindings['shared'][$abstract] ?? false;
    }

    protected function getConcrete($abstract)
    {
        return $this->bindings['concrete'][$abstract] ?? null;
    }

    /**
     * Resolve the given type from the container.
     */
    protected function resolve(string $abstract, array $parameters = [], bool $skipAliasReserved = false)
    {
        $shared = $this->isShared($abstract);
        $concrete = $this->getConcrete($abstract);

        // Manually resolve
        if ($concrete instanceof Closure) {
            $reflection = new \ReflectionFunction($concrete);
            $argCount = $reflection->getNumberOfParameters();

            if ($argCount === 0) {
                $instance = $concrete();
            } elseif ($argCount === 1) {
                $firstParameter = $reflection->getParameters()[0];
                $type = $firstParameter->getType();
                $name = $firstParameter->getName();
                $expectsArray = $type instanceof \ReflectionNamedType && $type->getName() === 'array';

                $instance = $expectsArray || in_array($name, ['parameters', 'params'], true)
                    ? $concrete($parameters)
                    : $concrete($this);
            } else {
                $instance = $concrete($this, $parameters);
            }

            return $this->resolveInstance($abstract, $instance, $shared);
        }
        if (is_object($concrete)) {
            return $this->resolveInstance($abstract, $concrete, $shared);
        }

        if ($this->isReserved($abstract)) {
            $parent = $this->reservedServices[$abstract];
            if ($concrete === null) {
                return $this->resolveInstance($abstract, new $parent(), true);
            }

            try {
                $reflectionClass = new \ReflectionClass($concrete);
            } catch (\ReflectionException $e) {
                throw new ContainerException("Class \"$abstract\" does not exist", 0, $e);
            }

            if ($concrete === $parent || $reflectionClass->isSubclassOf($parent)) {
                return $this->resolveInstance($abstract, new $concrete(), true);
            }
            throw new ContainerException("Class \"$abstract\" must be instance or sub-class of $parent");
        }

        if (!$skipAliasReserved && $this->isAliasReserved($abstract)) {
            $parent = $this->reservedServiceAlias[$abstract];

            return $this->resolveInstance($abstract, $this->make($parent), true);
        }

        if ($concrete === null) {
            $concrete = $abstract;
        }

        // Service-style keys (e.g. "db", "cache") should not accidentally
        // autowire class aliases via PHP's case-insensitive class resolution.
        if (
            $concrete === $abstract
            && !str_contains($abstract, '\\')
            && strtolower($abstract) === $abstract
        ) {
            throw new ContainerException("Service \"{$abstract}\" is not bound.");
        }

        $this->buildStack[] = $abstract;

        // Autowiring
        // @see https://www.youtube.com/watch?v=78Vpg97rQwE
        // 1. Inspect the class that we are trying to get from the container
        try {
            $reflectionClass = new \ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            array_pop($this->buildStack);
            throw new ContainerException("Class \"$abstract\" does not exist", 0, $e);
        }

        if (!$reflectionClass->isInstantiable()) {
            array_pop($this->buildStack);
            throw new ContainerException("Class \"$abstract\" is not instantiable");
        }

        // 2. Inspect the constructor of the class
        $constructor = $reflectionClass->getConstructor();

        if (!$constructor || $constructor->getNumberOfParameters() === 0) {
            $instance = $this->resolveInstance($abstract, new $concrete(), $shared);
            array_pop($this->buildStack);

            return $instance;
        }

        // 3. Inspect the constructor parameters (dependencies)
        // 4. If the constructor parameter is a class then try a resolve that class using the container
        $constructorParams = $constructor->getParameters();
        $dependencies = [];
        foreach ($constructorParams as $param) {
            $name = $param->getName();
            $type = $param->getType();

            // Caller-supplied named overrides win over autowiring.
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];

                continue;
            }

            if (($attribute = $this->getContextualAttributeFromDependency($param)) !== null) {
                $resolved = $this->resolveFromAttribute($attribute);

                if ($param->isVariadic()) {
                    foreach ($this->normalizeVariadicAttributeResolved($resolved) as $item) {
                        $dependencies[] = $item;
                    }
                } else {
                    $dependencies[] = $resolved;
                }

                $this->fireAfterResolvingAttributeCallbacks($param->getAttributes(), $resolved);

                continue;
            }

            if (!$type) {
                if ($param->isOptional()) {
                    try {
                        $dependencies[] = $param->getDefaultValue();
                    } catch (\ReflectionException $exception) {
                        // Internal-class default value cannot be reflected — stop autowiring further
                        // params and let PHP use the constructor's native defaults.
                        break;
                    }

                    continue;
                }
                if ($param->allowsNull()) {
                    $dependencies[] = null;

                    continue;
                }
                array_pop($this->buildStack);
                throw new ContainerException("Failed to resolve class \"$abstract\" because param '$name' is missing a type hint");
            }

            if ($type instanceof \ReflectionUnionType) {
                array_pop($this->buildStack);
                throw new ContainerException("Failed to resolve class \"$abstract\" because of union type for param '$name'");
            }

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencyType = $this->getAlias($type->getName());
                $contextualConcrete = $this->getContextualConcrete($dependencyType);

                if ($param->isVariadic()) {
                    if ($contextualConcrete === null) {
                        continue;
                    }

                    foreach ($this->resolveVariadicContextualDependencies($contextualConcrete) as $item) {
                        $dependencies[] = $item;
                    }

                    continue;
                }

                $dependencies[] = $contextualConcrete !== null
                    ? $this->resolveClassContextualDependency($contextualConcrete)
                    : $this->make($dependencyType);

                continue;
            }

            $primitiveContextual = $this->getContextualConcrete('$' . $name);
            if ($primitiveContextual !== null) {
                $dependencies[] = $primitiveContextual instanceof Closure
                    ? $primitiveContextual($this)
                    : $primitiveContextual;

                continue;
            }

            if ($param->allowsNull()) {
                $dependencies[] = null;

                continue;
            }
            if ($param->isOptional()) {
                try {
                    $defaultValue = $param->getDefaultValue();
                } catch (\ReflectionException $exception) {
                    array_pop($this->buildStack);
                    throw new ContainerException("Failed to resolve class \"$abstract\" because default value of param '$name' cannot be solved");
                }

                $dependencies[] = $defaultValue;

                continue;
            }

            array_pop($this->buildStack);
            throw new ContainerException("Failed to resolve class \"$abstract\" because invalid param '$name'");
        }

        $instance = $this->resolveInstance($abstract, $reflectionClass->newInstanceArgs($dependencies), $shared);
        array_pop($this->buildStack);

        return $instance;
    }

    /**
     * Register contextual binding builder for one or more concretes.
     *
     * @param string|array<int, string> $concrete
     */
    public function when(string|array $concrete): ContextualBindingBuilder
    {
        $concretes = is_array($concrete) ? $concrete : [$concrete];

        return new ContextualBindingBuilder($this, $concretes);
    }

    /**
     * Add contextual binding mapping.
     *
     * @param mixed $implementation
     */
    public function addContextualBinding(string $concrete, string $abstract, $implementation): void
    {
        if (!str_starts_with($abstract, '$')) {
            $abstract = $this->getAlias($abstract);
        }

        $this->contextual[$concrete][$abstract] = $implementation;
    }

    /**
     * Assign tags to one or more abstracts.
     *
     * @param array<int, string>|string $abstracts
     * @param array<int, string>|string $tags
     */
    public function tag(array|string $abstracts, array|string $tags): void
    {
        $abstractList = is_array($abstracts) ? $abstracts : [$abstracts];
        $tagList = is_array($tags) ? $tags : [$tags];

        foreach ($tagList as $tag) {
            $this->tags[$tag] ??= [];

            foreach ($abstractList as $abstract) {
                $this->tags[$tag][] = $abstract;
            }
        }
    }

    /**
     * Resolve all bindings for a given tag.
     *
     * @return iterable<int, mixed>
     */
    public function tagged(string $tag): iterable
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        foreach ($this->tags[$tag] as $abstract) {
            yield $this->make($abstract);
        }
    }

    protected function resolveInstance(string $abstract, $instance, bool $shared)
    {
        $this->phalconDi->set($abstract, $instance, $shared);

        if ($shared) {
            $this->resolved[$abstract] = true;
            $this->resolvedInstances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Fire resolving and after-resolving callbacks.
     *
     * @param mixed $instance
     */
    protected function fireResolvingCallbacks(string $abstract, $instance): void
    {
        // Keep $abstract observable to currentlyResolving() while callbacks
        // run. The container's resolve() has already popped its own push by
        // the time we reach here.
        $this->buildStack[] = $abstract;
        try {
            foreach ($this->globalResolvingCallbacks as $callback) {
                $callback($instance, $this);
            }

            foreach ($this->resolvingCallbacks[$abstract] ?? [] as $callback) {
                $callback($instance, $this);
            }

            if (is_object($instance) && ($instanceClass = get_class($instance)) !== $abstract) {
                foreach ($this->resolvingCallbacks[$instanceClass] ?? [] as $callback) {
                    $callback($instance, $this);
                }
            }

            $this->fireAfterResolvingCallbacks($abstract, $instance);
        } finally {
            array_pop($this->buildStack);
        }
    }

    /**
     * Fire after-resolving callbacks.
     *
     * @param mixed $instance
     */
    protected function fireAfterResolvingCallbacks(string $abstract, $instance): void
    {
        foreach ($this->afterResolvingCallbacks['*'] ?? [] as $callback) {
            $callback($instance, $this);
        }

        foreach ($this->afterResolvingCallbacks[$abstract] ?? [] as $callback) {
            $callback($instance, $this);
        }

        if (is_object($instance) && ($instanceClass = get_class($instance)) !== $abstract) {
            foreach ($this->afterResolvingCallbacks[$instanceClass] ?? [] as $callback) {
                $callback($instance, $this);
            }
        }
    }

    /**
     * Fire registered rebinding callbacks for abstract.
     */
    protected function fireReboundCallbacks(string $abstract): void
    {
        if (!isset($this->reboundCallbacks[$abstract])) {
            return;
        }

        $instance = $this->make($abstract);

        foreach ($this->reboundCallbacks[$abstract] as $callback) {
            $callback($this, $instance);
        }
    }

    /**
     * Get contextual concrete for the current build context and abstract.
     *
     * @return mixed
     */
    protected function getContextualConcrete(string $abstract)
    {
        $context = end($this->buildStack);
        if (!is_string($context)) {
            return;
        }

        // Direct match against the concrete class on the build stack.
        if (isset($this->contextual[$context][$abstract])) {
            return $this->contextual[$context][$abstract];
        }

        // Walk back from the concrete to any abstracts bound to it, then to any
        // aliases pointing at those abstracts, looking for a contextual binding.
        foreach ($this->collectContextKeysForBuildStack($context) as $key) {
            if (isset($this->contextual[$key][$abstract])) {
                return $this->contextual[$key][$abstract];
            }
        }
    }

    /**
     * Yield every contextual-binding key that should resolve when the supplied
     * concrete class is currently being built — abstracts that bind to it and
     * any aliases that reach those abstracts.
     *
     * @return iterable<string>
     */
    protected function collectContextKeysForBuildStack(string $context): iterable
    {
        $candidates = [];
        foreach ($this->bindings['concrete'] ?? [] as $abstract => $concrete) {
            if ($concrete === $context && $abstract !== $context) {
                $candidates[$abstract] = true;
            }
        }

        foreach (array_keys($candidates) as $abstract) {
            yield $abstract;
        }

        foreach ($this->aliases as $alias => $target) {
            if ($target === $context || isset($candidates[$target])) {
                yield $alias;
            }
        }
    }

    /**
     * Get contextual attribute metadata from a constructor dependency.
     */
    protected function getContextualAttributeFromDependency(\ReflectionParameter $dependency): ?\ReflectionAttribute
    {
        $contract = $dependency->getAttributes(
            ContextualAttributeContract::class,
            \ReflectionAttribute::IS_INSTANCEOF
        )[0] ?? null;

        if ($contract !== null) {
            return $contract;
        }

        // Fall back to attributes registered via whenHasAttribute().
        if ($this->attributeHandlers !== []) {
            foreach ($dependency->getAttributes() as $attribute) {
                if (isset($this->attributeHandlers[$attribute->getName()])) {
                    return $attribute;
                }
            }
        }

        return null;
    }

    /**
     * Resolve a dependency from contextual attribute metadata.
     */
    public function resolveFromAttribute(\ReflectionAttribute $attribute): mixed
    {
        $attributeClass = $attribute->getName();
        $instance = $attribute->newInstance();

        if (isset($this->attributeHandlers[$attributeClass])) {
            return ($this->attributeHandlers[$attributeClass])($instance, $this);
        }

        if (method_exists($attributeClass, 'resolve')) {
            return $attributeClass::resolve($instance, $this);
        }

        throw new \RuntimeException(sprintf(
            'Contextual attribute [%s] must define static resolve().',
            $attributeClass
        ));
    }

    /**
     * Register a custom resolver for the given attribute class.
     * Mirrors Laravel's `whenHasAttribute()`.
     */
    public function whenHasAttribute(string $attribute, Closure $handler): void
    {
        $this->attributeHandlers[$attribute] = $handler;
    }

    // Phalcon\Di\Di already implements get(string, $parameters): mixed,
    // which satisfies PSR-11 ContainerInterface::get(). We declare
    // `implements PsrContainerInterface` on the class header but do NOT
    // override get() here, because Phalcon's getShared() internally calls
    // $this->get() and an override would re-enter make() recursively.
    //
    // ArrayAccess offsetSet / offsetUnset / __set are also inherited from
    // Phalcon — overriding them broke existing internal service registration.

    /**
     * Convert a bare unknown-id make() error into a PSR-11 compliant
     * NotFoundException. Public sugar; callers preferring strict PSR-11
     * semantics should use this rather than the bare get() inherited
     * from Phalcon.
     */
    public function psrGet(string $id): mixed
    {
        $alias = $this->getAlias($id);
        if (!$this->bound($alias) && !class_exists($alias) && !interface_exists($alias)) {
            try {
                $hasService = $this->phalconDi->has($alias);
            } catch (\Throwable) {
                $hasService = false;
            }
            if (!$hasService) {
                throw new ServiceNotFoundException("Service [{$id}] not found in container.");
            }
        }

        return $this->make($id);
    }

    /**
     * Fire after-resolving callbacks registered against contextual attributes.
     *
     * @param array<int, \ReflectionAttribute> $reflectionAttributes
     */
    protected function fireAfterResolvingAttributeCallbacks(array $reflectionAttributes, mixed $object): void
    {
        foreach ($reflectionAttributes as $reflectionAttribute) {
            $name = $reflectionAttribute->getName();

            if (!is_a($name, ContextualAttributeContract::class, true)) {
                continue;
            }

            $callbacks = $this->afterResolvingAttributeCallbacks[$name] ?? [];
            if ($callbacks === []) {
                continue;
            }

            $instance = $reflectionAttribute->newInstance();
            foreach ($callbacks as $callback) {
                $callback($instance, $object, $this);
            }
        }
    }

    /**
     * Resolve a contextual class dependency definition.
     *
     * @param mixed $contextualConcrete
     * @return mixed
     */
    protected function resolveClassContextualDependency($contextualConcrete)
    {
        if ($contextualConcrete instanceof Closure) {
            return $contextualConcrete($this);
        }

        if (is_string($contextualConcrete)) {
            return $this->make($contextualConcrete);
        }

        return $contextualConcrete;
    }

    /**
     * Resolve contextual dependencies for variadic typed parameters.
     *
     * @param mixed $contextualConcrete
     * @return array<int, mixed>
     */
    protected function resolveVariadicContextualDependencies($contextualConcrete): array
    {
        if ($contextualConcrete instanceof Closure) {
            $contextualConcrete = $contextualConcrete($this);
        }

        if (is_string($contextualConcrete)) {
            return [$this->make($contextualConcrete)];
        }

        if (!is_array($contextualConcrete)) {
            return [$contextualConcrete];
        }

        $resolved = [];
        foreach ($contextualConcrete as $item) {
            if ($item instanceof Closure) {
                $resolved[] = $item($this);
            } elseif (is_string($item)) {
                $resolved[] = $this->make($item);
            } else {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }

    /**
     * Normalize contextual attribute resolved value for variadic injection.
     *
     * @return array<int, mixed>
     */
    protected function normalizeVariadicAttributeResolved(mixed $resolved): array
    {
        if (is_array($resolved)) {
            return $resolved;
        }

        if ($resolved instanceof \Traversable) {
            return iterator_to_array($resolved, false);
        }

        return [$resolved];
    }
}
