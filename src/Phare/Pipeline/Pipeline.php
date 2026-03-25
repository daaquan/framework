<?php

namespace Phare\Pipeline;

use Closure;
use Phare\Contracts\Foundation\Container;
use RuntimeException;
use Throwable;

class Pipeline
{
    protected ?Container $container;

    protected mixed $passable = null;

    /** @var array<int, mixed> */
    protected array $pipes = [];

    protected string $method = 'handle';

    protected ?Closure $finally = null;

    public function __construct(?Container $container = null)
    {
        $this->container = $container;
    }

    /**
     * Set the object being sent through the pipeline.
     */
    public function send(mixed $passable): static
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * Set the array of pipes.
     */
    public function through(mixed ...$pipes): static
    {
        $this->pipes = is_array($pipes[0] ?? null) ? $pipes[0] : $pipes;

        return $this;
    }

    /**
     * Push additional pipes onto the pipeline.
     */
    public function pipe(mixed ...$pipes): static
    {
        array_push($this->pipes, ...(is_array($pipes[0] ?? null) ? $pipes[0] : $pipes));

        return $this;
    }

    /**
     * Set the method to call on the pipes.
     */
    public function via(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    /**
     * Set a final callback to be executed after the pipeline ends regardless of the outcome.
     */
    public function finally(Closure $callback): static
    {
        $this->finally = $callback;

        return $this;
    }

    /**
     * Run the pipeline with a final destination callback.
     */
    public function then(Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes()),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        try {
            return $pipeline($this->passable);
        } finally {
            if ($this->finally) {
                ($this->finally)($this->passable);
            }
        }
    }

    /**
     * Run the pipeline and return the result.
     */
    public function thenReturn(): mixed
    {
        return $this->then(fn ($passable) => $passable);
    }

    /**
     * Get the final piece of the Closure onion.
     */
    protected function prepareDestination(Closure $destination): Closure
    {
        return function ($passable) use ($destination) {
            try {
                return $destination($passable);
            } catch (Throwable $e) {
                return $this->handleException($passable, $e);
            }
        };
    }

    /**
     * Get a Closure that represents a slice of the application onion.
     */
    protected function carry(): Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                try {
                    if (is_callable($pipe)) {
                        return $pipe($passable, $stack);
                    } elseif (!is_object($pipe)) {
                        [$name, $parameters] = $this->parsePipeString($pipe);

                        $pipe = $this->getContainer()->make($name);

                        $parameters = array_merge([$passable, $stack], $parameters);
                    } else {
                        $parameters = [$passable, $stack];
                    }

                    $carry = method_exists($pipe, $this->method)
                        ? $pipe->{$this->method}(...$parameters)
                        : $pipe(...$parameters);

                    return $this->handleCarry($carry);
                } catch (Throwable $e) {
                    return $this->handleException($passable, $e);
                }
            };
        };
    }

    /**
     * Parse full pipe string to get name and parameters.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    protected function parsePipeString(string $pipe): array
    {
        [$name, $parameters] = array_pad(explode(':', $pipe, 2), 2, null);

        if (!is_null($parameters)) {
            $parameters = explode(',', $parameters);
        } else {
            $parameters = [];
        }

        return [$name, $parameters];
    }

    /**
     * Get the array of configured pipes.
     *
     * @return array<int, mixed>
     */
    protected function pipes(): array
    {
        return $this->pipes;
    }

    /**
     * Get the container instance.
     *
     * @throws RuntimeException
     */
    protected function getContainer(): Container
    {
        if (!$this->container) {
            throw new RuntimeException('A container instance has not been passed to the Pipeline.');
        }

        return $this->container;
    }

    /**
     * Set the container instance.
     */
    public function setContainer(Container $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Handle the value returned from each pipe before passing it to the next.
     */
    protected function handleCarry(mixed $carry): mixed
    {
        return $carry;
    }

    /**
     * Handle the given exception.
     *
     * @throws Throwable
     */
    protected function handleException(mixed $passable, Throwable $e): mixed
    {
        throw $e;
    }
}
