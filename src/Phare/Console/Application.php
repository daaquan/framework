<?php

namespace Phare\Console;

use Phare\Container\Container;
use Phare\Contracts\Console\Application as ApplicationContract;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;

class Application extends SymfonyApplication implements ApplicationContract
{
    protected array $bootstrappers = [];

    protected ConsoleOutput|BufferedOutput|null $lastOutput = null;

    public function __construct(protected Container $app)
    {
        parent::__construct('Console', $app->version());
        $this->setAutoExit(false);
    }

    public function resolveCommands(array $commands): static
    {
        foreach ($commands as $command) {
            // Accept instances as well for DI compatibility
            $this->add(is_object($command) ? $command : new $command());
        }

        return $this;
    }

    public function call(string $command, array $parameters = [], $output = null): int
    {
        $input = new ArrayInput(['command' => $command] + $parameters);
        $this->lastOutput = $output ?: new BufferedOutput();

        return $this->run($input, $this->lastOutput);
    }

    public function output(): string
    {
        return $this->lastOutput && method_exists($this->lastOutput, 'fetch')
            ? $this->lastOutput->fetch()
            : '';
    }

    /**
     * Add --env and --language options to all commands.
     */
    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOption($this->getEnvironmentOption());
        $definition->addOption($this->getLanguageOption());

        return $definition;
    }

    protected function getEnvironmentOption(): InputOption
    {
        return new InputOption(
            '--env',
            null,
            InputOption::VALUE_OPTIONAL,
            'The environment the command should run under'
        );
    }

    protected function getLanguageOption(): InputOption
    {
        return new InputOption(
            '--language',
            null,
            InputOption::VALUE_OPTIONAL,
            'The language the command should run under'
        );
    }
}
