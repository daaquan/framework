<?php

use Phare\Config\ConfigEnvironment;

test('config environment can load base configs', function () {
    $configEnv = new ConfigEnvironment('testing');
    
    // Create temporary config directory
    $configPath = sys_get_temp_dir() . '/config_' . uniqid();
    mkdir($configPath);
    
    // Create base config files
    file_put_contents($configPath . '/app.php', '<?php return ["name" => "TestApp", "debug" => false];');
    file_put_contents($configPath . '/database.php', '<?php return ["default" => "mysql"];');
    
    $configs = $configEnv->load($configPath);
    
    expect($configs)->toHaveKey('app');
    expect($configs)->toHaveKey('database');
    expect($configs['app']['name'])->toBe('TestApp');
    expect($configs['database']['default'])->toBe('mysql');
    
    // Clean up
    unlink($configPath . '/app.php');
    unlink($configPath . '/database.php');
    rmdir($configPath);
});

test('config environment can load environment-specific configs', function () {
    $configEnv = new ConfigEnvironment('testing');
    
    // Create temporary config directory
    $configPath = sys_get_temp_dir() . '/config_' . uniqid();
    mkdir($configPath);
    
    // Create base and environment-specific config files
    file_put_contents($configPath . '/app.php', '<?php return ["name" => "TestApp", "debug" => false];');
    file_put_contents($configPath . '/app.testing.php', '<?php return ["debug" => true, "log_level" => "debug"];');
    
    $configs = $configEnv->load($configPath);
    
    expect($configs['app']['name'])->toBe('TestApp');
    expect($configs['app']['debug'])->toBeTrue(); // overridden by testing config
    expect($configs['app']['log_level'])->toBe('debug'); // added by testing config
    
    // Clean up
    unlink($configPath . '/app.php');
    unlink($configPath . '/app.testing.php');
    rmdir($configPath);
});

test('config environment merges configs correctly', function () {
    $configEnv = new ConfigEnvironment('staging');
    
    // Create temporary config directory
    $configPath = sys_get_temp_dir() . '/config_' . uniqid();
    mkdir($configPath);
    
    // Create base config
    $baseConfig = [
        'connections' => ['mysql' => ['host' => 'localhost']],
        'default' => 'mysql',
        'debug' => false,
    ];
    file_put_contents($configPath . '/database.php', '<?php return ' . var_export($baseConfig, true) . ';');
    
    // Create staging-specific config
    $stagingConfig = [
        'connections' => ['mysql' => ['host' => 'staging.db.com']],
        'debug' => true,
    ];
    file_put_contents($configPath . '/database.staging.php', '<?php return ' . var_export($stagingConfig, true) . ';');
    
    $configs = $configEnv->load($configPath);
    
    expect($configs['database']['default'])->toBe('mysql'); // preserved from base
    expect($configs['database']['debug'])->toBeTrue(); // overridden by staging
    expect($configs['database']['connections']['mysql']['host'])->toBe('staging.db.com'); // overridden by staging
    
    // Clean up
    unlink($configPath . '/database.php');
    unlink($configPath . '/database.staging.php');
    rmdir($configPath);
});

test('config environment can get environment name', function () {
    $configEnv = new ConfigEnvironment('production');
    
    expect($configEnv->getEnvironment())->toBe('production');
});

test('config environment can manage overrides', function () {
    $configEnv = new ConfigEnvironment('local');
    
    expect($configEnv->hasEnvironmentOverride('test.key'))->toBeFalse();
    
    $configEnv->setEnvironmentOverride('test.key', 'test.value');
    
    expect($configEnv->hasEnvironmentOverride('test.key'))->toBeTrue();
    expect($configEnv->getEnvironmentOverrides())->toHaveKey('test.key', 'test.value');
    
    $configEnv->removeEnvironmentOverride('test.key');
    
    expect($configEnv->hasEnvironmentOverride('test.key'))->toBeFalse();
});

test('config environment can create from detector', function () {
    $_ENV['APP_ENV'] = 'development';
    
    $configPath = sys_get_temp_dir() . '/config_' . uniqid();
    mkdir($configPath);
    
    $configEnv = ConfigEnvironment::createFromDetector($configPath);
    
    expect($configEnv->getEnvironment())->toBe('development');
    
    rmdir($configPath);
    unset($_ENV['APP_ENV']);
});

test('config environment ignores environment files in base config loading', function () {
    $configEnv = new ConfigEnvironment('testing');
    
    // Create temporary config directory
    $configPath = sys_get_temp_dir() . '/config_' . uniqid();
    mkdir($configPath);
    
    // Create base and environment-specific config files
    file_put_contents($configPath . '/app.php', '<?php return ["name" => "TestApp"];');
    file_put_contents($configPath . '/app.testing.php', '<?php return ["debug" => true];');
    file_put_contents($configPath . '/database.local.php', '<?php return ["driver" => "sqlite"];');
    
    $reflection = new ReflectionClass($configEnv);
    $method = $reflection->getMethod('loadBaseConfigs');
    $method->setAccessible(true);
    
    $configs = $method->invoke($configEnv, $configPath);
    
    // Should only load app.php, not the environment-specific files
    expect($configs)->toHaveKey('app');
    expect($configs)->not->toHaveKey('app.testing');
    expect($configs)->not->toHaveKey('database.local');
    
    // Clean up
    unlink($configPath . '/app.php');
    unlink($configPath . '/app.testing.php');
    unlink($configPath . '/database.local.php');
    rmdir($configPath);
});