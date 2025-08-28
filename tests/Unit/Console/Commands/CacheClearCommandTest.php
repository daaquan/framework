<?php

use Phare\Console\Commands\CacheClearCommand;

test('cache clear command is properly defined', function () {
    expect(class_exists(CacheClearCommand::class))->toBeTrue();
    expect(method_exists(CacheClearCommand::class, 'handle'))->toBeTrue();
});

test('cache clear command extends base command', function () {
    expect(is_subclass_of(CacheClearCommand::class, 'Phare\Console\Command'))->toBeTrue();
});