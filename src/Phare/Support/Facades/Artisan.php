<?php

namespace Phare\Support\Facades;

use Phare\Console\Kernel;
use Phare\Contracts\Console\Kernel as ConsoleKernelContract;
use Phare\Contracts\Foundation\Bus\PendingDispatch;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * @method static void registerCommand(Command $command)
 * @method static int call(string $command, array $parameters = [], ConsoleOutput|BufferedOutput|null $output = null)
 * @method static PendingDispatch queue(string $command, array $parameters = [])
 * @method static array all()
 * @method static string output()
 * @method static void setArtisan(Kernel $artisan)
 *
 * @see \Phare\Foundation\Http\Kernel
 */
class Artisan extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return ConsoleKernelContract::class;
    }
}
