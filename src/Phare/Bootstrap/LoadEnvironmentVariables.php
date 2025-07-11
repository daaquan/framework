<?php

declare(strict_types=1);

namespace Phare\Bootstrap;

use Phare\Contracts\Foundation\Application;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;
use Symfony\Component\Dotenv\Exception\PathException;

/**
 * Load environment variables from the .env file.
 */
class LoadEnvironmentVariables
{
    /**
     * Load the environment variables.
     *
     * Does nothing if the .env file does not exist.
     */
    public function bootstrap(Application $app): void
    {
        (new Dotenv())->usePutenv()
            ->load($app->basePath() . '/.env');
        return;
        try {
            (new Dotenv())->usePutenv()
                ->load($app->basePath() . '/.env');
        } catch (PathException $e) {
            $this->writeErrorAndDie([
                'The environment path is invalid!',
                $e->getMessage(),
            ]);
        } catch (FormatException $e) {
            $this->writeErrorAndDie([
                'The environment file format is invalid!',
                $e->getMessage(),
            ]);
        }
    }

    /**
     * Output errors and exit.
     */
    protected function writeErrorAndDie(array $errors): void
    {
        $output = (new ConsoleOutput())->getErrorOutput();

        foreach ($errors as $error) {
            $output->writeln($error);
        }

        exit(1);
    }
}
