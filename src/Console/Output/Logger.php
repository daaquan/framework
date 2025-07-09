<?php

namespace Phare\Console\Output;

class Logger extends Output
{
    /**
     * Log of received messages.
     *
     * @var array
     */
    protected $log = [];

    /**
     * Write a message.
     *
     * @param string $message
     */
    public function write($message)
    {
        if ($this->verbosity) {
            $this->log[] = $message;
        }
    }

    /**
     * Return true if log contains message.
     *
     * @param string $message
     * @return bool
     */
    public function hasMessage($message)
    {
        return in_array($message, $this->getLog());
    }

    /**
     * Return the log array.
     *
     * @param int $index
     * @return array
     */
    public function getLog($index = null)
    {
        if (is_null($index)) {
            return $this->log;
        }

        return $this->log[$index];
    }

    /**
     * Clear the log.
     */
    public function clearLog()
    {
        $this->log = [];
    }
}
