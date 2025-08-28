<?php

use Phare\Config\EnvironmentManager;
use Phare\Config\EnvironmentDetector;

test('environment manager can detect environment', function () {
    $detector = Mockery::mock(EnvironmentDetector::class);
    $detector->shouldReceive('detect')
        ->once()
        ->with(null)
        ->andReturn('testing');
    
    $manager = new EnvironmentManager($detector);
    
    // Create a temporary directory for testing
    $basePath = sys_get_temp_dir() . '/phare_test_' . uniqid();
    mkdir($basePath);
    
    $environment = $manager->detect($basePath);
    
    expect($environment)->toBe('testing');
    expect($manager->getEnvironment())->toBe('testing');
    
    // Clean up
    rmdir($basePath);
});

test('environment manager can check environment types', function () {
    $detector = Mockery::mock(EnvironmentDetector::class);
    $detector->shouldReceive('detect')->andReturn('local');
    
    $manager = new EnvironmentManager($detector);
    $manager->detect(sys_get_temp_dir());
    
    expect($manager->isEnvironment('local'))->toBeTrue();
    expect($manager->isEnvironment('production'))->toBeFalse();
    expect($manager->isEnvironment('local', 'development'))->toBeTrue();
    expect($manager->isDevelopment())->toBeTrue();
    expect($manager->isProduction())->toBeFalse();
    expect($manager->isTesting())->toBeFalse();
    expect($manager->isStaging())->toBeFalse();
});

test('environment manager can check production environment', function () {
    $detector = Mockery::mock(EnvironmentDetector::class);
    $detector->shouldReceive('detect')->andReturn('production');
    
    $manager = new EnvironmentManager($detector);
    $manager->detect(sys_get_temp_dir());
    
    expect($manager->isProduction())->toBeTrue();
    expect($manager->isDevelopment())->toBeFalse();
});

test('environment manager can check testing environment', function () {
    $detector = Mockery::mock(EnvironmentDetector::class);
    $detector->shouldReceive('detect')->andReturn('testing');
    
    $manager = new EnvironmentManager($detector);
    $manager->detect(sys_get_temp_dir());
    
    expect($manager->isTesting())->toBeTrue();
    expect($manager->isProduction())->toBeFalse();
});

test('environment manager can load environment files', function () {
    // Clear any existing environment variables from previous tests
    unset($_ENV['TEST_VAR'], $_ENV['LOCAL_VAR'], $_ENV['SHARED_VAR']);
    unset($_SERVER['TEST_VAR'], $_SERVER['LOCAL_VAR'], $_SERVER['SHARED_VAR']);
    putenv('TEST_VAR');
    putenv('LOCAL_VAR');  
    putenv('SHARED_VAR');
    
    $detector = Mockery::mock(EnvironmentDetector::class);
    $detector->shouldReceive('detect')->andReturn('local');
    
    $manager = new EnvironmentManager($detector);
    
    // Create temporary directory and files
    $basePath = sys_get_temp_dir() . '/phare_test_' . uniqid();
    mkdir($basePath);
    
    file_put_contents($basePath . '/.env', "TEST_VAR=base_value\nSHARED_VAR=base");
    file_put_contents($basePath . '/.env.local', "TEST_VAR=local_value\nLOCAL_VAR=local");
    
    $manager->detect($basePath);
    
    // Check that environment variables were loaded
    expect($_ENV['TEST_VAR'] ?? null)->toBe('local_value'); // local overrides base
    expect($_ENV['LOCAL_VAR'] ?? null)->toBe('local');
    expect($_ENV['SHARED_VAR'] ?? null)->toBe('base');
    
    // Clean up
    unlink($basePath . '/.env');
    unlink($basePath . '/.env.local');
    rmdir($basePath);
    
    // Clean up environment
    unset($_ENV['TEST_VAR'], $_ENV['LOCAL_VAR'], $_ENV['SHARED_VAR']);
    unset($_SERVER['TEST_VAR'], $_SERVER['LOCAL_VAR'], $_SERVER['SHARED_VAR']);
});

test('environment manager can set custom environment files', function () {
    $detector = Mockery::mock(EnvironmentDetector::class);
    $detector->shouldReceive('detect')->andReturn('custom');
    
    $manager = new EnvironmentManager($detector);
    $manager->setEnvironmentFiles(['.env', '.env.custom']);
    
    // Create temporary directory and files
    $basePath = sys_get_temp_dir() . '/phare_test_' . uniqid();
    mkdir($basePath);
    
    file_put_contents($basePath . '/.env.custom', 'CUSTOM_VAR=custom_value');
    
    $manager->detect($basePath);
    
    expect($_ENV['CUSTOM_VAR'] ?? null)->toBe('custom_value');
    
    // Clean up
    unlink($basePath . '/.env.custom');
    rmdir($basePath);
    unset($_ENV['CUSTOM_VAR'], $_SERVER['CUSTOM_VAR']);
});

test('environment manager can add environment files', function () {
    $manager = new EnvironmentManager();
    $manager->addEnvironmentFile('.env.extra');
    
    $reflection = new ReflectionClass($manager);
    $property = $reflection->getProperty('environmentFiles');
    $property->setAccessible(true);
    
    $files = $property->getValue($manager);
    
    expect($files)->toContain('.env.extra');
});

test('environment manager provides access to detector', function () {
    $detector = new EnvironmentDetector();
    $manager = new EnvironmentManager($detector);
    
    expect($manager->getDetector())->toBe($detector);
});