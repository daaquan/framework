<?php

use Phare\Console\Commands\RouteClearCommand;

test('route clear command is properly defined', function () {
    expect(class_exists(RouteClearCommand::class))->toBeTrue();
    expect(method_exists(RouteClearCommand::class, 'handle'))->toBeTrue();
});

test('route clear command extends base command', function () {
    expect(is_subclass_of(RouteClearCommand::class, 'Phare\Console\Command'))->toBeTrue();
});