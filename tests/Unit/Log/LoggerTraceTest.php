<?php

use Phalcon\Logger\Adapter\Stream;
use Phalcon\Logger\Logger as BaseLogger;
use Phare\Log\Logger;

it('writes trace messages through the Phalcon logger', function () {
    $path = tempnam(sys_get_temp_dir(), 'phare-trace-');
    $adapter = new Stream($path);
    $base = new BaseLogger('trace-test', ['main' => $adapter]);
    $base->setLogLevel(BaseLogger::TRACE);

    try {
        $logger = new Logger($base);
        $logger->trace('Trace message');

        expect(file_get_contents($path))
            ->toContain('[trace]')
            ->toContain('Trace message');
    } finally {
        $adapter->close();
        unlink($path);
    }
})->skip(!method_exists(BaseLogger::class, 'trace'), 'Requires Phalcon trace logging support.');
