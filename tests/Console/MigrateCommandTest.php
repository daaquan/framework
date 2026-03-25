<?php

use Phare\Console\Commands\MigrateCommand;

it('can instantiate migrate command', function () {
    $command = new MigrateCommand();

    expect($command)->toBeInstanceOf(MigrateCommand::class);
});

it('has expected command metadata', function () {
    $command = new MigrateCommand();

    $reflection = new ReflectionClass($command);
    $signature = $reflection->getProperty('signature');
    $signature->setAccessible(true);
    $description = $reflection->getProperty('description');
    $description->setAccessible(true);

    expect($signature->getValue($command))->toContain('migrate');
    expect($description->getValue($command))->toBe('Run database migrations');
});
