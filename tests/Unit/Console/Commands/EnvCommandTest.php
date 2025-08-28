<?php

use Phare\Console\Commands\EnvCommand;

test('env command shows current environment', function () {
    $app = Mockery::mock('Phare\Contracts\Foundation\Application');
    $app->shouldReceive('environment')->andReturn('testing');
    
    $command = new EnvCommand();
    $command->setApplication($app);
    
    // Mock output methods
    $command = Mockery::mock(EnvCommand::class)->makePartial();
    $command->shouldReceive('info')->once()->with('Current environment: <comment>testing</comment>');
    $command->shouldReceive('option')->with('show')->andReturn(false);
    
    $result = $command->handle();
    
    expect($result)->toBe(0);
});

test('env command can show environment variables', function () {
    // Set some test environment variables
    $_ENV['TEST_VAR'] = 'test_value';
    $_ENV['SECRET_KEY'] = 'secret_value';
    $_ENV['PUBLIC_VAR'] = 'public_value';
    
    $app = Mockery::mock('Phare\Contracts\Foundation\Application');
    $app->shouldReceive('environment')->andReturn('testing');
    
    $command = Mockery::mock(EnvCommand::class)->makePartial();
    $command->shouldReceive('info')->once();
    $command->shouldReceive('option')->with('show')->andReturn(true);
    $command->shouldReceive('line')->with('');
    $command->shouldReceive('line')->with('Environment Variables:');
    $command->shouldReceive('line')->with('=====================');
    
    // Should hide secret variables
    $command->shouldReceive('line')->with(Mockery::pattern('/<comment>SECRET_KEY<\/comment>=<info>\*+<\/info>/'));
    $command->shouldReceive('line')->with('<comment>PUBLIC_VAR</comment>=<info>public_value</info>');
    $command->shouldReceive('line')->with('<comment>TEST_VAR</comment>=<info>test_value</info>');
    
    $command->setApplication($app);
    
    $result = $command->handle();
    
    expect($result)->toBe(0);
    
    // Clean up
    unset($_ENV['TEST_VAR'], $_ENV['SECRET_KEY'], $_ENV['PUBLIC_VAR']);
});

test('env command hides sensitive variables', function () {
    $command = new EnvCommand();
    
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('shouldHideVariable');
    $method->setAccessible(true);
    
    $hiddenPatterns = [
        '*_SECRET*',
        '*_KEY*',
        '*_PASSWORD*',
        '*_TOKEN*',
        '*_PRIVATE*',
    ];
    
    expect($method->invoke($command, 'API_SECRET', $hiddenPatterns))->toBeTrue();
    expect($method->invoke($command, 'DATABASE_KEY', $hiddenPatterns))->toBeTrue();
    expect($method->invoke($command, 'USER_PASSWORD', $hiddenPatterns))->toBeTrue();
    expect($method->invoke($command, 'ACCESS_TOKEN', $hiddenPatterns))->toBeTrue();
    expect($method->invoke($command, 'PRIVATE_KEY', $hiddenPatterns))->toBeTrue();
    
    expect($method->invoke($command, 'APP_NAME', $hiddenPatterns))->toBeFalse();
    expect($method->invoke($command, 'DATABASE_HOST', $hiddenPatterns))->toBeFalse();
    expect($method->invoke($command, 'PUBLIC_URL', $hiddenPatterns))->toBeFalse();
});