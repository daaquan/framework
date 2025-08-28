<?php

use Phare\Console\Commands\ViewClearCommand;

test('view clear command is properly defined', function () {
    expect(class_exists(ViewClearCommand::class))->toBeTrue();
    expect(method_exists(ViewClearCommand::class, 'handle'))->toBeTrue();
});

test('view clear command extends base command', function () {
    expect(is_subclass_of(ViewClearCommand::class, 'Phare\Console\Command'))->toBeTrue();
});