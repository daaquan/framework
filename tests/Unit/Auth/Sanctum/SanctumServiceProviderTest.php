<?php

use Phare\Auth\Sanctum\SanctumServiceProvider;
use Phare\Support\ServiceProvider;

// Regression guard: the provider used to extend Phare\Providers\ServiceProvider,
// a class that does not exist, so merely autoloading it was a fatal error.
it('extends the real service provider base class', function () {
    expect(class_exists(SanctumServiceProvider::class))->toBeTrue()
        ->and(is_subclass_of(SanctumServiceProvider::class, ServiceProvider::class))->toBeTrue();
});

it('only calls methods that exist on its base class', function () {
    $declared = get_class_methods(SanctumServiceProvider::class);

    expect($declared)->toContain('register')
        ->and($declared)->toContain('boot')
        ->and(method_exists(SanctumServiceProvider::class, 'loadMigrationsFrom'))->toBeFalse();
});
