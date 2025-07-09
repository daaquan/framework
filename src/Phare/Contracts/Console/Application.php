<?php

namespace Phare\Contracts\Console;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface Application
{
    /**
     * Run an Artisan console command by name.
     *
     * @param ConsoleOutputInterface|OutputInterface|null $output
     * @return int
     */
    public function call(string $command, array $parameters = [], $output = null);

    /**
     * Get the output from the last command.
     *
     * @return string
     */
    public function output();
}
