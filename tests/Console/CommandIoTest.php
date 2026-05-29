<?php

use Phare\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class IoTestCommand extends Command
{
    protected ?string $signature = 'io:test';

    protected ?string $description = 'Exercises the Laravel-style IO surface';

    public function handle(): int
    {
        $this->warn('careful');
        $this->newLine(2);
        $this->table(['Name', 'Age'], [['Alice', '30']]);

        return 0;
    }
}

function runIoCommand(): string
{
    $cmd = new IoTestCommand();
    $output = new BufferedOutput();
    $cmd->run(new ArrayInput([], $cmd->getDefinition()), $output);

    return $output->fetch();
}

test('warn() writes the message', function () {
    expect(runIoCommand())->toContain('careful');
});

test('newLine() emits blank lines', function () {
    expect(substr_count(runIoCommand(), "\n"))->toBeGreaterThanOrEqual(2);
});

test('table() renders headers and rows', function () {
    $out = runIoCommand();

    expect($out)->toContain('Name')
        ->and($out)->toContain('Age')
        ->and($out)->toContain('Alice')
        ->and($out)->toContain('30');
});

test('secret() exists as an interactive helper', function () {
    expect(method_exists(Command::class, 'secret'))->toBeTrue();
});
