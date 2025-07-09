<?php

it('tests register method', function () {
    $app = $this->createMock(\Phare\Foundation\Micro::class);
    $app->expects($this->once())
        ->method('basePath')
        ->willReturn($_ENV['APP_BASE_PATH']);

    $loadEnvironmentVariables = new \Phare\Foundation\Bootstrap\LoadEnvironmentVariables();
    $loadEnvironmentVariables->register($app);

    $loader = $this->createMock(\Phare\Bootstrap\LoadEnvironmentVariables::class);
    $loader->expects($this->once())
        ->method('bootstrap')
        ->with($this->identicalTo($app));
    $loader->bootstrap($app);

    $appName = '';
    // Extract APP_NAME from the .env file
    $envFile = $_ENV['APP_BASE_PATH'] . '/.env';
    if (file_exists($envFile)) {
        $env = file_get_contents($envFile);
        $env = explode("\n", $env);
        foreach ($env as $line) {
            if (str_contains($line, 'APP_NAME')) {
                $appName = explode('=', $line)[1];
                break;
            }
        }
    }
    $this->assertEquals($appName, $_SERVER['APP_NAME']);
});
