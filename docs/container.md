# Service Container

The Phare service container (`Phare\Container\Container`) extends Phalcon's DI with
Laravel-compatible binding semantics.

## Basic bindings

```php
// Bind a concrete class
$app->bind(Mailer::class, SmtpMailer::class);

// Bind with a factory closure
$app->bind(LoggerInterface::class, function ($app) {
    return new FileLogger($app->make('config')->get('logging.path'));
});

// Singleton (resolved once, instance reused)
$app->singleton(Cache::class, function ($app) {
    return new RedisCache($app->make('redis'));
});

// Register only if not already bound
$app->bindIf(LoggerInterface::class, FileLogger::class);
$app->singletonIf(Cache::class, RedisCache::class);
```

## Resolving

```php
$mailer = $app->make(Mailer::class);

// With extra parameters
$report = $app->make(Report::class, ['format' => 'pdf']);
```

## Aliases

```php
$app->alias(Mailer::class, 'mailer');

$mailer = $app->make('mailer'); // resolves to Mailer::class
```

## Contextual bindings

Inject different implementations depending on which class is being constructed.

```php
$app->when(OrderController::class)
    ->needs(Mailer::class)
    ->give(SmtpMailer::class);

$app->when(InvoiceController::class)
    ->needs(Mailer::class)
    ->give(LogMailer::class);
```

### Primitive injection

```php
$app->when(PaymentGateway::class)
    ->needs('$apiKey')
    ->give(fn ($app) => $app->make('config')->get('payment.api_key'));

// Or inject a config value directly
$app->when(PaymentGateway::class)
    ->needs('$apiKey')
    ->giveConfig('payment.api_key', 'default-key');
```

### Variadic dependencies

```php
$app->when(ReportAggregator::class)
    ->needs(ReportInterface::class)
    ->give([SalesReport::class, InventoryReport::class]);
```

## Tags

```php
$app->tag([SalesReport::class, InventoryReport::class], 'reports');

$reports = $app->tagged('reports'); // iterable
```

### Contextual tagged injection

```php
$app->when(ReportAggregator::class)
    ->needs(ReportInterface::class)
    ->giveTagged('reports');
```

## Resolving callbacks

```php
// Called every time any class is resolved
$app->resolving(function ($object, $app) {
    // ...
});

// Called every time a specific class is resolved
$app->resolving(Mailer::class, function ($mailer, $app) {
    $mailer->setFrom($app->make('config')->get('mail.from'));
});

// Called after resolution
$app->afterResolving(Mailer::class, function ($mailer) {
    $mailer->initialize();
});
```

## Rebinding callbacks

Fired when an existing binding is replaced:

```php
$app->rebinding(Mailer::class, function ($app, $mailer) {
    // update dependents...
});
```

## Checking bindings

```php
$app->bound(Mailer::class);    // bool — has a binding been registered?
$app->resolved(Mailer::class); // bool — has it been resolved at least once?
$app->isShared(Mailer::class); // bool — is it a singleton?
$app->getAlias('mailer');      // resolves alias chain to concrete class string
```
