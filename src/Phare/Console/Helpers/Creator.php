<?php

namespace Phare\Console\Helpers;

use Phare\Console\Config;
use Phare\Console\Output\Output;

abstract class Creator
{
    use Filesystem;

    /**
     * Phalcon config.
     */
    protected Config $config;

    /**
     * Output strategy.
     */
    protected Output $output;

    /**
     * Construct.
     */
    public function __construct(Output $output)
    {
        $this->output = $output;

        $this->config = Config::getInstance();
    }

    /**
     * Set the namespace in the command stub.
     */
    protected function setNamespace(string $stub, ?string $namespace = null): string
    {
        if ($namespace !== null) {
            return str_replace(
                'NAMESPACE',
                "\nnamespace {$namespace};\n",
                $stub
            );
        }

        return str_replace('NAMESPACE', '', $stub);
    }

    /**
     * Output nothing created message if no all bools are false.
     */
    protected function outputNothingCreated(array $bools): void
    {
        foreach ($bools as $bool) {
            if ($bool) {
                return;
            }
        }

        $this->output->writeComment(
            'Nothing created. All directories and files already exist.'
        );
    }
}
