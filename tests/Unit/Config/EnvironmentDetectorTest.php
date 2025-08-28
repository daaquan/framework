<?php

use Phare\Config\EnvironmentDetector;

test('environment detector can detect from environment variable', function () {
    $_ENV['APP_ENV'] = 'testing';
    
    $detector = new EnvironmentDetector();
    $environment = $detector->detect();
    
    expect($environment)->toBe('testing');
    
    unset($_ENV['APP_ENV']);
});

test('environment detector can detect from hostname', function () {
    $environments = [
        'local' => ['*.local', 'localhost'],
        'staging' => ['*.staging', 'stage-*'],
        'production' => ['prod.example.com'],
    ];
    
    $detector = new EnvironmentDetector($environments);
    
    // Mock hostname detection
    $reflection = new ReflectionClass($detector);
    $method = $reflection->getMethod('matchesPattern');
    $method->setAccessible(true);
    
    expect($method->invoke($detector, 'app.local', '*.local'))->toBeTrue();
    expect($method->invoke($detector, 'localhost', 'localhost'))->toBeTrue();
    expect($method->invoke($detector, 'stage-api', 'stage-*'))->toBeTrue();
    expect($method->invoke($detector, 'prod.example.com', 'prod.example.com'))->toBeTrue();
    expect($method->invoke($detector, 'unknown.host', '*.local'))->toBeFalse();
});

test('environment detector can detect from command line arguments', function () {
    // Store original state
    $originalEnv = $_ENV['APP_ENV'] ?? null;
    $originalServer = $_SERVER['APP_ENV'] ?? null;
    global $argv;
    $originalArgv = $argv;
    
    // Clear environment variables so they don't take precedence
    unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
    putenv('APP_ENV');
    
    $argv = ['script.php', '--env=development', 'other-arg'];
    
    $detector = new EnvironmentDetector();
    $environment = $detector->detect();
    
    expect($environment)->toBe('development');
    
    // Restore original state
    $argv = $originalArgv;
    if ($originalEnv !== null) $_ENV['APP_ENV'] = $originalEnv;
    if ($originalServer !== null) $_SERVER['APP_ENV'] = $originalServer;
});

test('environment detector returns production as default', function () {
    // Store original state
    $originalEnv = $_ENV['APP_ENV'] ?? null;
    $originalServer = $_SERVER['APP_ENV'] ?? null;
    global $argv;
    $originalArgv = $argv;
    
    // Clear all environment detection methods
    unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
    putenv('APP_ENV');
    $argv = [];
    
    $detector = new EnvironmentDetector();
    $environment = $detector->detect();
    
    expect($environment)->toBe('production');
    
    // Restore original state
    $argv = $originalArgv;
    if ($originalEnv !== null) $_ENV['APP_ENV'] = $originalEnv;
    if ($originalServer !== null) $_SERVER['APP_ENV'] = $originalServer;
});

test('environment detector can use custom callback', function () {
    $detector = new EnvironmentDetector();
    
    $environment = $detector->detect(function () {
        return 'custom';
    });
    
    expect($environment)->toBe('custom');
});

test('environment detector can set and get environments', function () {
    $environments = [
        'local' => ['*.local'],
        'staging' => ['*.staging'],
    ];
    
    $detector = new EnvironmentDetector();
    $detector->setEnvironments($environments);
    
    expect($detector->getEnvironments())->toBe($environments);
});

test('environment detector matches wildcard patterns correctly', function () {
    $detector = new EnvironmentDetector();
    
    $reflection = new ReflectionClass($detector);
    $method = $reflection->getMethod('matchesPattern');
    $method->setAccessible(true);
    
    // Test various wildcard patterns
    expect($method->invoke($detector, 'api.local', '*.local'))->toBeTrue();
    expect($method->invoke($detector, 'app.local', '*.local'))->toBeTrue();
    expect($method->invoke($detector, 'local.dev', '*.dev'))->toBeTrue();
    expect($method->invoke($detector, 'dev-server', 'dev-*'))->toBeTrue();
    expect($method->invoke($detector, 'staging-api', '*-api'))->toBeTrue();
    
    // Negative tests
    expect($method->invoke($detector, 'production.com', '*.local'))->toBeFalse();
    expect($method->invoke($detector, 'api-prod', 'dev-*'))->toBeFalse();
});