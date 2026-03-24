<?php

use Phare\Container\Container;
use Phare\Container\Attributes\Config as ConfigAttribute;
use Phare\Container\Attributes\Tag as TagAttribute;

class ContainerCompatibilityFoo
{
    public function __construct(public string $value = 'foo')
    {
    }
}

interface ContainerCompatibilityLoggerInterface
{
}

class ContainerCompatibilityNullLogger implements ContainerCompatibilityLoggerInterface
{
}

class ContainerCompatibilityFileLogger implements ContainerCompatibilityLoggerInterface
{
}

class ContainerCompatibilityServiceA
{
    public function __construct(public ContainerCompatibilityLoggerInterface $logger)
    {
    }
}

class ContainerCompatibilityServiceB
{
    public function __construct(public ContainerCompatibilityLoggerInterface $logger)
    {
    }
}

class ContainerCompatibilityPrimitiveService
{
    public function __construct(public string $region)
    {
    }
}

class ContainerCompatibilityVariadicService
{
    /** @var array<int, ContainerCompatibilityLoggerInterface> */
    public array $loggers;

    public function __construct(ContainerCompatibilityLoggerInterface ...$loggers)
    {
        $this->loggers = $loggers;
    }
}

class ContainerCompatibilityConfigPrimitiveService
{
    public function __construct(public string $timezone)
    {
    }
}

class ContainerCompatibilityAttributedConfigService
{
    public function __construct(
        #[ConfigAttribute('app.timezone', 'UTC')]
        public string $timezone
    ) {
    }
}

class ContainerCompatibilityAttributedTagService
{
    /** @var array<int, object> */
    public array $loggers;

    public function __construct(
        #[TagAttribute('loggers')]
        object ...$loggers
    ) {
        $this->loggers = $loggers;
    }
}

it('fires resolving callbacks for global and abstract callbacks', function () {
    $container = new Container();
    $events = [];

    $container->bind('foo', fn (array $parameters = []) => new ContainerCompatibilityFoo());

    $container->resolving(function ($instance, Container $app) use (&$events) {
        $events[] = 'global:' . $instance::class;
    });
    $container->resolving('foo', function ($instance, Container $app) use (&$events) {
        $events[] = 'abstract:' . $instance::class;
    });

    $container->make('foo');

    expect($events)->toBe([
        'global:' . ContainerCompatibilityFoo::class,
        'abstract:' . ContainerCompatibilityFoo::class,
    ]);
});

it('fires after resolving callback immediately if abstract already resolved', function () {
    $container = new Container();
    $container->singleton('foo', fn (array $parameters = []) => new ContainerCompatibilityFoo('resolved'));

    $container->make('foo');
    $events = [];

    $container->afterResolving('foo', function ($instance, Container $app) use (&$events) {
        $events[] = $instance->value;
    });

    expect($events)->toBe(['resolved']);
});

it('fires rebinding callbacks when existing binding is replaced', function () {
    $container = new Container();
    $container->singleton('foo', fn (array $parameters = []) => new ContainerCompatibilityFoo('first'));
    $container->make('foo');

    $values = [];
    $container->rebinding('foo', function (Container $app, $instance) use (&$values) {
        $values[] = $instance->value;
    });

    $container->bind('foo', fn (array $parameters = []) => new ContainerCompatibilityFoo('second'), true);

    expect($values)->toContain('first');
    expect($values)->toContain('second');
});

it('resolves chained aliases to root abstract', function () {
    $container = new Container();
    $container->bind('root', fn (array $parameters = []) => 'ok');
    $container->alias('root', 'a1');
    $container->alias('a1', 'a2');

    expect($container->getAlias('a2'))->toBe('root');
    expect($container->make('a2'))->toBe('ok');
});

it('supports contextual binding with when-needs-give', function () {
    $container = new Container();
    $container->bind(
        ContainerCompatibilityLoggerInterface::class,
        ContainerCompatibilityNullLogger::class
    );

    $container->when(ContainerCompatibilityServiceA::class)
        ->needs(ContainerCompatibilityLoggerInterface::class)
        ->give(ContainerCompatibilityFileLogger::class);

    $serviceA = $container->make(ContainerCompatibilityServiceA::class);
    $serviceB = $container->make(ContainerCompatibilityServiceB::class);

    expect($serviceA->logger)->toBeInstanceOf(ContainerCompatibilityFileLogger::class);
    expect($serviceB->logger)->toBeInstanceOf(ContainerCompatibilityNullLogger::class);
});

it('supports contextual binding with closure give', function () {
    $container = new Container();
    $container->bind(
        ContainerCompatibilityLoggerInterface::class,
        ContainerCompatibilityNullLogger::class
    );

    $container->when(ContainerCompatibilityServiceA::class)
        ->needs(ContainerCompatibilityLoggerInterface::class)
        ->give(fn (Container $app) => new ContainerCompatibilityFileLogger());

    $serviceA = $container->make(ContainerCompatibilityServiceA::class);

    expect($serviceA->logger)->toBeInstanceOf(ContainerCompatibilityFileLogger::class);
});

it('supports contextual binding for primitive constructor parameters', function () {
    $container = new Container();

    $container->when(ContainerCompatibilityPrimitiveService::class)
        ->needs('$region')
        ->give('ap-northeast-1');

    $service = $container->make(ContainerCompatibilityPrimitiveService::class);

    expect($service->region)->toBe('ap-northeast-1');
});

it('supports contextual binding for variadic class dependencies', function () {
    $container = new Container();
    $container->bind(
        ContainerCompatibilityLoggerInterface::class,
        ContainerCompatibilityNullLogger::class
    );

    $container->when(ContainerCompatibilityVariadicService::class)
        ->needs(ContainerCompatibilityLoggerInterface::class)
        ->give([
            ContainerCompatibilityFileLogger::class,
            ContainerCompatibilityNullLogger::class,
        ]);

    $service = $container->make(ContainerCompatibilityVariadicService::class);

    expect($service->loggers)->toHaveCount(2);
    expect($service->loggers[0])->toBeInstanceOf(ContainerCompatibilityFileLogger::class);
    expect($service->loggers[1])->toBeInstanceOf(ContainerCompatibilityNullLogger::class);
});

it('resolves contextual binding when needs key is an alias of abstract', function () {
    $container = new Container();
    $container->bind(
        ContainerCompatibilityLoggerInterface::class,
        ContainerCompatibilityNullLogger::class
    );
    $container->alias(ContainerCompatibilityLoggerInterface::class, 'logger.alias');

    $container->when(ContainerCompatibilityServiceA::class)
        ->needs('logger.alias')
        ->give(ContainerCompatibilityFileLogger::class);

    $serviceA = $container->make(ContainerCompatibilityServiceA::class);
    $serviceB = $container->make(ContainerCompatibilityServiceB::class);

    expect($serviceA->logger)->toBeInstanceOf(ContainerCompatibilityFileLogger::class);
    expect($serviceB->logger)->toBeInstanceOf(ContainerCompatibilityNullLogger::class);
});

it('resolves tagged services via tagged()', function () {
    $container = new Container();
    $container->bind('logger.file', ContainerCompatibilityFileLogger::class);
    $container->bind('logger.null', ContainerCompatibilityNullLogger::class);
    $container->tag(['logger.file', 'logger.null'], 'loggers');

    $resolved = iterator_to_array($container->tagged('loggers'));

    expect($resolved)->toHaveCount(2);
    expect($resolved[0])->toBeInstanceOf(ContainerCompatibilityFileLogger::class);
    expect($resolved[1])->toBeInstanceOf(ContainerCompatibilityNullLogger::class);
});

it('supports contextual giveTagged for variadic dependencies', function () {
    $container = new Container();
    $container->bind('logger.file', ContainerCompatibilityFileLogger::class);
    $container->bind('logger.null', ContainerCompatibilityNullLogger::class);
    $container->tag(['logger.file', 'logger.null'], 'loggers');

    $container->when(ContainerCompatibilityVariadicService::class)
        ->needs(ContainerCompatibilityLoggerInterface::class)
        ->giveTagged('loggers');

    $service = $container->make(ContainerCompatibilityVariadicService::class);

    expect($service->loggers)->toHaveCount(2);
    expect($service->loggers[0])->toBeInstanceOf(ContainerCompatibilityFileLogger::class);
    expect($service->loggers[1])->toBeInstanceOf(ContainerCompatibilityNullLogger::class);
});

it('supports contextual giveConfig for primitive dependencies', function () {
    $container = new Container();
    $container->singleton('config', fn (array $parameters = []) => new class()
    {
        private array $values = [
            'app.timezone' => 'Asia/Tokyo',
        ];

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }
    });

    $container->when(ContainerCompatibilityConfigPrimitiveService::class)
        ->needs('$timezone')
        ->giveConfig('app.timezone', 'UTC');

    $service = $container->make(ContainerCompatibilityConfigPrimitiveService::class);
    expect($service->timezone)->toBe('Asia/Tokyo');
});

it('falls back to default in contextual giveConfig when key is missing', function () {
    $container = new Container();
    $container->singleton('config', fn (array $parameters = []) => new class()
    {
        public function get(string $key, mixed $default = null): mixed
        {
            return $default;
        }
    });

    $container->when(ContainerCompatibilityConfigPrimitiveService::class)
        ->needs('$timezone')
        ->giveConfig('app.timezone', 'UTC');

    $service = $container->make(ContainerCompatibilityConfigPrimitiveService::class);
    expect($service->timezone)->toBe('UTC');
});

it('supports config contextual attribute injection', function () {
    $container = new Container();
    $container->singleton('config', fn (array $parameters = []) => new class()
    {
        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'app.timezone' ? 'Asia/Seoul' : $default;
        }
    });

    $service = $container->make(ContainerCompatibilityAttributedConfigService::class);

    expect($service->timezone)->toBe('Asia/Seoul');
});

it('supports tag contextual attribute injection for variadic dependencies', function () {
    $container = new Container();
    $container->bind('logger.file', ContainerCompatibilityFileLogger::class);
    $container->bind('logger.null', ContainerCompatibilityNullLogger::class);
    $container->tag(['logger.file', 'logger.null'], 'loggers');

    $service = $container->make(ContainerCompatibilityAttributedTagService::class);

    expect($service->loggers)->toHaveCount(2);
    expect($service->loggers[0])->toBeInstanceOf(ContainerCompatibilityFileLogger::class);
    expect($service->loggers[1])->toBeInstanceOf(ContainerCompatibilityNullLogger::class);
});
