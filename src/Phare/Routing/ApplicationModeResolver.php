<?php

declare(strict_types=1);

namespace Phare\Routing;

use Phare\Contracts\Foundation\Application;
use Phare\Foundation\Micro;
use Phare\Foundation\Web;

class ApplicationModeResolver
{
    public const MODE_WEB = 'web';
    public const MODE_MICRO = 'micro';

    public function resolve(Application $app): string
    {
        if ($app instanceof Web) {
            return self::MODE_WEB;
        }

        if ($app instanceof Micro) {
            return self::MODE_MICRO;
        }

        throw new \RuntimeException(sprintf(
            'Application class "%s" not supported.',
            get_class($app)
        ));
    }
}
