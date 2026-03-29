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
use Phare\Contracts\Container\ContextualAttribute as ContextualAttributeContract;
use Phare\Contracts\Foundation\Container as ContractsContainer;
use TypeError;

class Container extends Di implements ContractsContainer
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

    protected array $aliases = [];

    protected array $bindings = [
        'concrete' => [],
        'shared' => [],
    ];

    protected array $resolved = [];

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
     * @var array<int, \Closure>
     */
    protected array $globalResolvingCallbacks = [];

    /**
     * Callbacks fired when a specific abstract is resolved.
     *
     * @var array<string, array<int, \Closure>>
     */
    protected array $resolvingCallbacks = [];

    /**
     * Callbacks fired after resolving a specific abstract.
     *
     * @var array<string, array<int, \Closure>>
     */
    protected array $afterResolvingCallbacks = [];

    /**
     * Callbacks fired when an abstract is rebound.
     *
     * @var array<string, array<int, \Closure>>
     */
    protected array $reboundCallbacks = [];

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
     * Register a binding with the container.
     *
     * @throws TypeError
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        $isRebind = $this->bound($abstract) || $this->resolved($abstract);

        if ($isRebind) {
            $this->remove($abstract);
            unset($this->resolved[$abstract]);
        }

        if ($shared) {
            $this->bindings['shared'][$abstract] = true;
        }

        $this->set($abstract, $concrete, $shared);
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

        if ($this->resolved($abstract)) {
            $getter = $this->isShared($abstract) ? 'getShared' : 'get';

            $instance = $this->$getter($abstract, $parameters);
            $this->fireResolvingCallbacks($abstract, $instance);

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
                    $service = $this->getService($abstract);
                    if ($service->isShared()) {
                        $instance = $this->getShared($abstract, $parameters);
                        $this->resolved[$abstract] = true;
                        if (is_object($instance)) {
                            $instanceClass = get_class($instance);
                            if ($instanceClass !== $abstract) {
                                $this->aliases[$instanceClass] = $abstract;
                            }
                        }

                        return $instance;
                    }
                } catch (Exception $e) {
                    // Service not found in Phalcon DI, continue to resolve
                }
            }
        }

        $instance = $this->resolve($abstract, $parameters);

        if (is_object($instance)) {
            $instanceClass = get_class($instance);
            if ($instanceClass !== $abstract) {
                $this->aliases[$instanceClass] = $abstract;
            }
        }

        $this->fireResolvingCallbacks($abstract, $instance);

        return $instance;
    }

    public function resolved(string $abstract): bool
    {
        return $this->resolved[$abstract] ?? false;
    }

    /**
     * Register a resolving callback.
     *
     * @param string|\Closure $abstract
     */
    public function resolving($abstract, ?\Closure $callback = null): void
    {
        if ($abstract instanceof \Closure && $callback === null) {
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
     * @param string|\Closure $abstract
     */
    public function afterResolving($abstract, ?\Closure $callback = null): void
    {
        if ($abstract instanceof \Closure && $callback === null) {
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
     * Register a rebinding callback.
     */
    public function rebinding(string $abstract, \Closure $callback): void
    {
        $this->reboundCallbacks[$abstract][] = $callback;

        if ($this->bound($abstract)) {
            $this->fireReboundCallbacks($abstract);
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
    protected function resolve(string $abstract, array $parameters = [])
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

        if ($this->isAliasReserved($abstract)) {
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
        $parameters = $constructor->getParameters();
        $dependencies = [];
        foreach ($parameters as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if (($attribute = $this->getContextualAttributeFromDependency($param)) !== null) {
                $resolved = $this->resolveFromAttribute($attribute);

                if ($param->isVariadic()) {
                    foreach ($this->normalizeVariadicAttributeResolved($resolved) as $item) {
                        $dependencies[] = $item;
                    }
                } else {
                    $dependencies[] = $resolved;
                }

                continue;
            }

            if (!$type) {
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
        $this->set($abstract, $instance, $shared);

        if ($shared) {
            $this->resolved[$abstract] = true;
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
        foreach ($this->globalResolvingCallbacks as $callback) {
            $callback($instance, $this);
        }

        foreach ($this->resolvingCallbacks[$abstract] ?? [] as $callback) {
            $callback($instance, $this);
        }

        if (is_object($instance)) {
            foreach ($this->resolvingCallbacks[get_class($instance)] ?? [] as $callback) {
                $callback($instance, $this);
            }
        }

        $this->fireAfterResolvingCallbacks($abstract, $instance);
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

        if (is_object($instance)) {
            foreach ($this->afterResolvingCallbacks[get_class($instance)] ?? [] as $callback) {
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
            return null;
        }

        return $this->contextual[$context][$abstract] ?? null;
    }

    /**
     * Get contextual attribute metadata from a constructor dependency.
     */
    protected function getContextualAttributeFromDependency(\ReflectionParameter $dependency): ?\ReflectionAttribute
    {
        return $dependency->getAttributes(
            ContextualAttributeContract::class,
            \ReflectionAttribute::IS_INSTANCEOF
        )[0] ?? null;
    }

    /**
     * Resolve a dependency from contextual attribute metadata.
     */
    public function resolveFromAttribute(\ReflectionAttribute $attribute): mixed
    {
        $attributeClass = $attribute->getName();
        $instance = $attribute->newInstance();

        if (method_exists($attributeClass, 'resolve')) {
            return $attributeClass::resolve($instance, $this);
        }

        throw new \RuntimeException(sprintf(
            'Contextual attribute [%s] must define static resolve().',
            $attributeClass
        ));
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
