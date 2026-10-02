<?php

use Phare\Bootstrap\LoadEnvironmentVariables;
use Phare\Contracts\Foundation\Application;
use Phare\Foundation\Bootstrap\LoadEnvironmentVariables as RegisterEnvironmentVariables;
use Phare\Foundation\Micro;

class EnvironmentLoaderWithCapturedErrors extends LoadEnvironmentVariables
{
    protected function writeErrorAndDie(array $errors): void
    {
        throw new RuntimeException(implode("\n", $errors));
    }
}

beforeEach(function () {
    $this->basePath = sys_get_temp_dir() . '/phare-env-' . bin2hex(random_bytes(6));
    mkdir($this->basePath);
    $this->previousEnv = $_ENV;
    $this->previousServer = $_SERVER;
    $this->previousProcessEnv = [];
    foreach (['PHARE_TEST_DOTENV_VALUE', 'SYMFONY_DOTENV_VARS', 'SYMFONY_DOTENV_PATH'] as $key) {
        $this->previousProcessEnv[$key] = getenv($key);
    }
    putenv('PHARE_TEST_DOTENV_VALUE');
    unset($_ENV['PHARE_TEST_DOTENV_VALUE'], $_SERVER['PHARE_TEST_DOTENV_VALUE']);
});

afterEach(function () {
    foreach ($this->previousProcessEnv as $key => $value) {
        putenv($value === false ? $key : $key . '=' . $value);
    }
    $_ENV = $this->previousEnv;
    $_SERVER = $this->previousServer;

    if (file_exists($this->basePath . '/.env')) {
        unlink($this->basePath . '/.env');
    }
    rmdir($this->basePath);
});

it('loads environment values through the bootstrap and registration entry points', function (bool $register) {
    file_put_contents($this->basePath . '/.env', 'PHARE_TEST_DOTENV_VALUE="Phare test value"' . "\n");
    $app = $this->createMock($register ? Micro::class : Application::class);
    $app->expects($this->once())->method('basePath')->willReturn($this->basePath);

    if ($register) {
        (new RegisterEnvironmentVariables())->register($app);
    } else {
        (new LoadEnvironmentVariables())->bootstrap($app);
    }

    expect($_ENV['PHARE_TEST_DOTENV_VALUE'])->toBe('Phare test value')
        ->and($_SERVER['PHARE_TEST_DOTENV_VALUE'])->toBe('Phare test value')
        ->and(getenv('PHARE_TEST_DOTENV_VALUE'))->toBe('Phare test value');
})->with(['bootstrap' => false, 'register' => true]);

it('reports a missing environment file', function () {
    $app = $this->createMock(Application::class);
    $app->method('basePath')->willReturn($this->basePath);

    expect(fn () => (new EnvironmentLoaderWithCapturedErrors())->bootstrap($app))
        ->toThrow(RuntimeException::class, 'The environment path is invalid!');

    expect(getenv('PHARE_TEST_DOTENV_VALUE'))->toBeFalse();
});

it('reports malformed environment values', function () {
    file_put_contents($this->basePath . '/.env', 'PHARE_TEST_DOTENV_VALUE="unterminated');
    $app = $this->createMock(Application::class);
    $app->method('basePath')->willReturn($this->basePath);

    expect(fn () => (new EnvironmentLoaderWithCapturedErrors())->bootstrap($app))
        ->toThrow(RuntimeException::class, 'The environment file format is invalid!');

    expect(getenv('PHARE_TEST_DOTENV_VALUE'))->toBeFalse();
});
