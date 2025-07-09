<?php

namespace Phare\Console;

use Phare\Console\Application as Artisan;
use Phare\Contracts\Console\Kernel as ConsoleKernel;
use Phare\Contracts\Foundation\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Kernel implements ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     */
    protected array $commands = [];

    /**
     * The bootstrap classes for the application.
     */
    protected array $bootstrappers = [];

    /**
     * A list of queued commands and their parameters.
     */
    protected array $commandQueue = [];

    /**
     * The Artisan application instance.
     */
    protected ?Artisan $artisan = null;

    public function __construct(protected Application $app)
    {
        $this->bootstrap();
    }

    public function handle(InputInterface $input, ?OutputInterface $output = null): int
    {
        return $this->getArtisan()->run($input, $output);
    }

    public function bootstrap()
    {
        if ($this->app->hasBeenBootstrapped()) {
            return;
        }

        $this->app->bootstrapWith($this->bootstrappers);
    }

    public function call(string $command, array $parameters = [], $output = null)
    {
        return $this->getArtisan()->call($command, $parameters, $output);
    }

    public function output()
    {
        return $this->getArtisan()->output();
    }

    public function queue($command, array $parameters = [])
    {
        $this->commandQueue[] = [
            'command' => $command,
            'parameters' => $parameters,
        ];
    }

    /**
     * Return all registered commands.
     */
    public function all()
    {
        return $this->commands;
    }

    public function terminate(InputInterface $input, int $status)
    {
        // For this example, just reset the last output and command queue.
        // In a more complex application, you may want to do more cleanup or
        // logging tasks here.
        $this->commandQueue = [];
    }

    /**
     * Get the Artisan application instance.
     *
     * @return \Phare\Console\Application
     */
    protected function getArtisan()
    {
        return $this->artisan ?? ($this->artisan = (new Artisan($this->app))
            ->resolveCommands($this->commands));
    }

    /**
     * Set the Artisan application instance.
     *
     * @return void
     */
    public function setArtisan(Artisan $artisan)
    {
        $this->artisan = $artisan;
    }
}
