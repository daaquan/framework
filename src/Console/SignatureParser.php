<?php

namespace Phare\Console;

use Phare\Console\Input\Argument;
use Phare\Console\Input\Option;

class SignatureParser
{
    public function __construct(protected Command $command) {}

    /**
     * Parse the command signature and register arguments/options.
     */
    public function parse(string $signature): void
    {
        $this->setName($signature);

        foreach ($this->extractArgumentsOptions($signature) as $value) {
            $input = str_starts_with($value, '--')
                ? new Option(ltrim($value, '-'))
                : new Argument($value);

            // addInput works with both Argument and Option
            $this->command->addInput($input->parse());
        }
    }

    /** Set the command name (first word before any space) */
    protected function setName(string $signature): void
    {
        $this->command->setName(strtok($signature, " \t") ?: '');
    }

    /**
     * Extract all {...} entries as arguments/options from signature.
     */
    protected function extractArgumentsOptions(string $signature): array
    {
        preg_match_all('/{(.*?)}/', $signature, $matches);

        return array_map('trim', $matches[1]);
    }
}
