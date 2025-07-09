<?php

namespace Phare\Console\Helpers;

use Exception;
use Phare\Console\Exceptions\WriteError;
use Phare\Console\Output\Output;

trait Filesystem
{
    /**
     * Create all directories listed in directories array.
     * Return the number of created directories.
     */
    protected function makeDirectoryStructure(array $directories, ?Output $output = null): int
    {
        $createdDirs = 0;

        foreach ($directories as $key => $directory) {
            if (file_exists($directory)) {
                continue;
            }

            if (mkdir($directory) || is_dir($directory)) {
                $createdDirs++;
            }
            if ($output !== null) {
                $output->writeInfo("Created {$key} directory.");
            }
        }

        return $createdDirs;
    }

    /**
     * Write contents to path.
     *
     *
     * @throws WriteError
     */
    protected function writeFile(string $path, string $contents): void
    {
        try {
            file_put_contents($path, $contents);
        } catch (Exception $e) {
            throw WriteError::fileWriteFailed($e, $path);
        }
    }
}
