<?php

use Phare\Console\Commands\ConfigClearCommand;

test('config clear command is properly defined', function () {
    expect(class_exists(ConfigClearCommand::class))->toBeTrue();
    expect(method_exists(ConfigClearCommand::class, 'handle'))->toBeTrue();
});

test('config clear command extends base command', function () {
    expect(is_subclass_of(ConfigClearCommand::class, 'Phare\Console\Command'))->toBeTrue();
});