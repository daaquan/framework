<?php

use Phare\Console\Command;
use Phare\Console\Concerns\AgentFriendly;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Minimal concrete command used for testing the AgentFriendly trait.
 */
class AgentTestCommand extends Command
{
    use AgentFriendly;

    protected ?string $signature = 'test:agent';

    protected ?string $description = 'Test command for AgentFriendly trait';

    public string $lastLine = '';

    public function handle(): int
    {
        $this->agentSet('project', 'phare');
        $this->agentSuccess('OK', ['version' => '1.0']);

        return 0;
    }

    // Expose initAgentMode for direct testing
    public function exposeInit(): void
    {
        $this->initAgentMode();
    }

    public function exposeIsJson(): bool
    {
        return $this->isJsonMode();
    }

    public function exposeIsNonInteractive(): bool
    {
        return $this->isNonInteractive();
    }
}

it('registers --json and --no-interactive options via configure()', function () {
    $cmd = new AgentTestCommand();
    $definition = $cmd->getDefinition();

    expect($definition->hasOption('json'))->toBeTrue();
    expect($definition->hasOption('no-interactive'))->toBeTrue();
});

it('isJsonMode() returns false by default', function () {
    $cmd = new AgentTestCommand();
    $input = new ArrayInput([], $cmd->getDefinition());
    $cmd->run($input, new BufferedOutput());

    // After a normal run without --json the mode should be false
    expect($cmd->exposeIsJson())->toBeFalse();
});

it('isJsonMode() returns true when --json is passed', function () {
    $cmd = new AgentTestCommand();
    $input = new ArrayInput(['--json' => true], $cmd->getDefinition());
    $output = new BufferedOutput();
    $cmd->run($input, $output);

    expect($cmd->exposeIsJson())->toBeTrue();
});

it('outputs valid JSON when --json flag is used', function () {
    $cmd = new AgentTestCommand();
    $input = new ArrayInput(['--json' => true], $cmd->getDefinition());
    $output = new BufferedOutput();
    $cmd->run($input, $output);

    $raw = trim($output->fetch());
    $decoded = json_decode($raw, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($decoded['status'])->toBe('success');
    expect($decoded['message'])->toBe('OK');
    expect($decoded['project'])->toBe('phare');
    expect($decoded['version'])->toBe('1.0');
});

it('isNonInteractive() returns true when --no-interactive is passed', function () {
    $cmd = new AgentTestCommand();
    $input = new ArrayInput(['--no-interactive' => true], $cmd->getDefinition());
    $output = new BufferedOutput();
    $cmd->run($input, $output);

    expect($cmd->exposeIsNonInteractive())->toBeTrue();
});

it('agentError() emits structured JSON in json mode', function () {
    $cmd = new AgentTestCommand();
    $input = new ArrayInput(['--json' => true], $cmd->getDefinition());
    $output = new BufferedOutput();

    // Override handle to produce an error
    $cmd = new class() extends AgentTestCommand
    {
        public function handle(): int
        {
            $this->agentError('Something went wrong', 'DEPLOY_FAILED', ['hint' => 'check logs']);

            return 1;
        }
    };
    $cmd->run($input, $output);

    $raw = trim($output->fetch());
    $decoded = json_decode($raw, true);

    expect($decoded['status'])->toBe('error');
    expect($decoded['code'])->toBe('DEPLOY_FAILED');
    expect($decoded['hint'])->toBe('check logs');
});
