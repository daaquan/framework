<?php

namespace Phare\Contracts\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface Kernel
{
    /**
     * Bootstrap the application for artisan commands.
     *
     * @return void
     */
    public function bootstrap();

    /**
     * Handle an incoming console command.
     */
    public function handle(InputInterface $input, ?OutputInterface $output = null): int;

    /**
     * Run an Artisan console command by name.
     *
     * @param ConsoleOutputInterface|OutputInterface|null $output
     * @return int
     */
    public function call(string $command, array $parameters = [], $output = null);

    /**
     * Queue an Artisan console command by name.
     *
     * @param string $command
     */
    public function queue($command, array $parameters = []);

    /**
     * Get all of the commands registered with the console.
     *
     * @return array
     */
    public function all();

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output();

    /**
     * Terminate the application.
     *
     * @return void
     */
    public function terminate(InputInterface $input, int $status);
}
