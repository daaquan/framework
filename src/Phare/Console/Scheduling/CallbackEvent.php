<?php

namespace Phare\Console\Scheduling;

class CallbackEvent extends Event
{
    protected \Closure $callback;

    protected string $description = 'Callback';

    public function __construct(string $timezone, callable $callback)
    {
        $this->callback = $callback instanceof \Closure ? $callback : \Closure::fromCallable($callback);
        parent::__construct($timezone, $this->description);
    }

    /**
     * Set the human-friendly description of the event.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Run the callback event.
     */
    protected function runCommand(): string
    {
        try {
            $result = ($this->callback)();

            $output = is_string($result) ? $result : 'Callback executed successfully';

            if ($this->output) {
                $mode = $this->shouldAppendOutput ? 'a' : 'w';
                file_put_contents($this->output, $output . PHP_EOL, $mode === 'a' ? FILE_APPEND : 0);
            }

            return $output;
        } catch (\Exception $e) {
            $error = 'Callback failed: ' . $e->getMessage();

            if ($this->output) {
                $mode = $this->shouldAppendOutput ? 'a' : 'w';
                file_put_contents($this->output, $error . PHP_EOL, $mode === 'a' ? FILE_APPEND : 0);
            }

            throw $e;
        }
    }

    /**
     * Get the command summary for display purposes.
     */
    public function getCommand(): string
    {
        return $this->description;
    }
}
