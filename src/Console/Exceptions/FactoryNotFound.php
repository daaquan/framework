<?php

namespace Phare\Console\Exceptions;

use Exception;

class FactoryNotFound extends Exception
{
    /**
     * Factory definition can not be found.
     *
     * @param string $message
     * @return static
     */
    public static function factoryDefinitionNotFound($message)
    {
        return new static($message);
    }
}
