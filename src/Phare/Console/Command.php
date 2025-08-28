<?php

namespace Phare\Console;

use Phare\Console\Input\Input;
use Phare\Console\Output\SymfonyOutput;
use ReflectionClass;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class Command extends SymfonyCommand
{
    protected SymfonyOutput $output;

    protected InputInterface $input;

    protected ?string $description = null;

    protected ?string $signature = null;

    protected Config $config;

    protected int $exitCode = 0;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
        $this->config = Config::getInstance();
    }

    public function addInput(Input $input): void
    {
        $method = 'add' . (new ReflectionClass($input))->getShortName();
        if (method_exists($this, $method)) {
            $this->$method(...array_values($input->getAttributes()));
        }
    }

    /** Confirmation prompt */
    public function confirm(string $text): bool
    {
        return $this->askQuestion(new ConfirmationQuestion($text, false));
    }

    /** Standard question */
    public function ask(string $question, $default = null): mixed
    {
        return $this->askQuestion(new Question($question, $default));
    }

    /** Password prompt */
    public function askPassword(string $question): mixed
    {
        $q = new Question($question);
        $q->setHidden(true)->setHiddenFallback(false);

        return $this->askQuestion($q);
    }

    /** Choose one from a list */
    public function choose(string $question, array $choices, $default = null): mixed
    {
        $q = new ChoiceQuestion($question, $choices, $default);
        $q->setErrorMessage('Option %s is invalid.');

        return $this->askQuestion($q);
    }

    /** Choose multiple from a list */
    public function choice(string $question, array $choices, $default = null): mixed
    {
        $q = new ChoiceQuestion($question, $choices, $default);
        $q->setMultiselect(true);

        return $this->askQuestion($q);
    }

    /** Autocomplete question */
    public function anticipate(string $question, array $autoCompletion, $default = null): mixed
    {
        $q = new Question($question, $default);
        $q->setAutocompleterValues($autoCompletion);

        return $this->askQuestion($q);
    }

    /** Ask a question using the helper */
    protected function askQuestion($question): mixed
    {
        return $this->getHelper('question')->ask($this->input, $this->getOutputInterface(), $question);
    }

    /** Get the Symfony OutputInterface */
    protected function getOutputInterface(): OutputInterface
    {
        return $this->output->getOutput();
    }

    protected function getOutput(): SymfonyOutput
    {
        return $this->output;
    }

    protected function configure(): void
    {
        if ($this->signature !== null) {
            $parser = new SignatureParser($this);
            $parser->parse($this->signature);
        }
        if ($this->description) {
            $this->setDescription($this->description);
        }
    }

    /** Execute the command */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = new SymfonyOutput($output);
        $this->input = $input;

        if (method_exists($this, 'handle')) {
            $this->handle();
        }

        return $this->exitCode ?? 0;
    }

    /** Retrieve command options */
    protected function option(?string $key = null): mixed
    {
        return $key === null
            ? $this->input->getOptions()
            : $this->input->getOption($key);
    }

    /** Retrieve command arguments */
    protected function argument(?string $key = null): mixed
    {
        return $key === null
            ? $this->input->getArguments()
            : $this->input->getArgument($key);
    }

    protected function hasArgument(string|int $name): bool
    {
        return $this->input->hasArgument($name);
    }

    protected function hasOption(string $name): bool
    {
        return $this->input->hasOption($name);
    }

    // Laravel-style output methods
    protected function info(string $message): void
    {
        $this->output->writeInfo($message);
    }

    protected function error(string $message): void
    {
        $this->output->writeError($message);
    }

    protected function comment(string $message): void
    {
        $this->output->writeComment($message);
    }

    protected function line(string $message = ''): void
    {
        $this->output->write($message);
    }
}
