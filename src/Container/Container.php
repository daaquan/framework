<?php

namespace Phare\Container;

use Closure;
use Phalcon\Di\Di;
use Phare\Container\Exceptions\ContainerException;
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
        'config' => \Phalcon\Config\ConfigInterface::class,
        'dispatcher' => \Phalcon\Mvc\DispatcherInterface::class,
        'router' => \Phalcon\Mvc\RouterInterface::class,
        'url' => \Phalcon\Mvc\Url\UrlInterface::class,
        'request' => \Phalcon\Http\RequestInterface::class,
        'response' => \Phalcon\Http\ResponseInterface::class,
        'cookies' => \Phalcon\Http\Response\CookiesInterface::class,
        'filter' => \Phalcon\Filter\Filter::class,
        'flashDirect' => \Phalcon\Flash\Direct::class,
        'flashSession' => \Phalcon\Flash\Session::class,
        'session' => \Phalcon\Session\ManagerInterface::class,
        'eventsManager' => \Phalcon\Events\ManagerInterface::class,
        'pdo' => \Phalcon\Db\Adapter\AdapterInterface::class,
        'security' => \Phalcon\Encryption\Security::class,
        'encrypter' => \Phalcon\Encryption\Crypt\CryptInterface::class,
        'tag' => \Phalcon\Html\TagFactory::class,
        'escaper' => \Phalcon\Html\Escaper\EscaperInterface::class,
        'annotations' => \Phalcon\Annotations\Annotation::class,
        'modelsManager' => \Phalcon\Mvc\Model\ManagerInterface::class,
        'modelsMetadata' => \Phalcon\Mvc\Model\MetadataInterface::class,
        'modelTransaction' => \Phalcon\Mvc\Model\Transaction\ManagerInterface::class,
        'assets' => \Phalcon\Assets\Manager::class,
        'di' => \Phalcon\Di\DiInterface::class,
        'sessionBag' => \Phalcon\Session\BagInterface::class,
        'view' => \Phalcon\Mvc\ViewInterface::class,
        'translator' => \Phalcon\Translate\Adapter\AbstractAdapter::class,
    ];

    /**
     * Phalcon standard services
     *
     * @var string[]
     */
    protected array $reservedServiceAlias = [
        \Phalcon\Config\ConfigInterface::class => 'config',
        \Phalcon\Mvc\DispatcherInterface::class => 'dispatcher',
        \Phalcon\Mvc\RouterInterface::class => 'router',
        \Phalcon\Mvc\Url\UrlInterface::class => 'url',
        \Phalcon\Http\RequestInterface::class => 'request',
        \Phalcon\Http\ResponseInterface::class => 'response',
        \Phalcon\Http\Response\CookiesInterface::class => 'cookies',
        \Phalcon\Filter\Filter::class => 'filter',
        \Phalcon\Flash\Direct::class => 'flashDirect',
        \Phalcon\Flash\Session::class => 'flashSession',
        \Phalcon\Session\ManagerInterface::class => 'session',
        \Phalcon\Events\ManagerInterface::class => 'eventsManager',
        \Phalcon\Db\Adapter\AdapterInterface::class => 'pdo',
        \Phalcon\Encryption\Security::class => 'security',
        \Phalcon\Encryption\Crypt\CryptInterface::class => 'encrypter',
        \Phalcon\Html\TagFactory::class => 'tag',
        \Phalcon\Html\Escaper\EscaperInterface::class => 'escaper',
        \Phalcon\Annotations\Annotation::class => 'annotations',
        \Phalcon\Mvc\Model\ManagerInterface::class => 'modelsManager',
        \Phalcon\Mvc\Model\MetadataInterface::class => 'modelsMetadata',
        \Phalcon\Mvc\Model\Transaction\ManagerInterface::class => 'modelTransaction',
        \Phalcon\Assets\Manager::class => 'assets',
        \Phalcon\Di\DiInterface::class => 'di',
        \Phalcon\Session\BagInterface::class => 'sessionBag',
        \Phalcon\Mvc\ViewInterface::class => 'view',
        \Phalcon\Translate\Adapter\AbstractAdapter::class => 'translator',
    ];

    protected array $aliases = [];

    protected array $bindings = [
        'concrete' => [],
        'shared' => [],
    ];

    protected array $resolved = [];

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
        if ($shared) {
            $this->bindings['shared'][$abstract] = true;
        }

        $this->set($abstract, $concrete, $shared);
        $this->bindings['concrete'][$abstract] = $concrete;
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
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        if ($this->resolved($abstract)) {
            $getter = $this->isShared($abstract) ? 'getShared' : 'get';

            return $this->$getter($abstract, $parameters);
        }

        $instance = $this->resolve($abstract, $parameters);

        if (is_object($instance)) {
            $this->aliases[get_class($instance)] = $abstract;
        }

        return $instance;
    }

    public function resolved(string $abstract): bool
    {
        return $this->resolved[$abstract] ?? false;
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
            return $this->resolveInstance($abstract, $concrete($parameters), $shared);
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

        // Autowiring
        // @see https://www.youtube.com/watch?v=78Vpg97rQwE
        // 1. Inspect the class that we are trying to get from the container
        try {
            $reflectionClass = new \ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new ContainerException("Class \"$abstract\" does not exist", 0, $e);
        }

        if (!$reflectionClass->isInstantiable()) {
            throw new ContainerException("Class \"$abstract\" is not instantiable");
        }

        // 2. Inspect the constructor of the class
        $constructor = $reflectionClass->getConstructor();

        if (!$constructor || $constructor->getNumberOfParameters() === 0) {
            return $this->resolveInstance($abstract, new $concrete(), $shared);
        }

        // 3. Inspect the constructor parameters (dependencies)
        // 4. If the constructor parameter is a class then try a resolve that class using the container
        $parameters = $constructor->getParameters();
        $dependencies = array_map(function (\ReflectionParameter $param) use ($abstract, $shared) {
            $name = $param->getName();
            $type = $param->getType();

            if (!$type) {
                throw new ContainerException("Failed to resolve class \"$abstract\" because param '$name' is missing a type hint");
            }

            if ($type instanceof \ReflectionUnionType) {
                throw new ContainerException("Failed to resolve class \"$abstract\" because of union type for param '$name'");
            }

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $this->set($abstract, $instance = $this->make($type->getName()), $shared);

                if ($this->isShared($abstract)) {
                    $this->resolved[$abstract] = true;
                }

                return $instance;
            }

            if ($param->allowsNull()) {
                return;
            }
            if ($param->isOptional()) {
                try {
                    $defaultValue = $param->getDefaultValue();
                } catch (\ReflectionException $exception) {
                    throw new ContainerException("Failed to resolve class \"$abstract\" because default value of param '$name' cannot be solved");
                }

                return $defaultValue;
            }

            throw new ContainerException("Failed to resolve class \"$abstract\" because invalid param '$name'");
        }, $parameters);

        return $this->resolveInstance($abstract, $reflectionClass->newInstanceArgs($dependencies), $shared);
    }

    protected function resolveInstance(string $abstract, $instance, bool $shared)
    {
        $this->set($abstract, $instance, $shared);

        if ($shared) {
            $this->resolved[$abstract] = true;
        }

        return $instance;
    }
}
