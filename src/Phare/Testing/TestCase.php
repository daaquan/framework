<?php

namespace Phare\Testing;

use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;
use Phare\Foundation\AbstractApplication as Application;
use Phare\Foundation\Testing\Concerns\MakesHttpRequests;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use MakesHttpRequests;

    protected ?Application $app = null;

    abstract public function createApplication(): Application;

    public function setUpApplication(): void
    {
        if (!defined('APP_RUNNING_UNIT_TEST')) {
            define('APP_RUNNING_UNIT_TEST', true);
        }

        Di::reset();

        $app = $this->createApplication();
        $app->bootstrapWith([
            \Phare\Foundation\Bootstrap\LoadEnvironmentVariables::class,
            \Phare\Foundation\Bootstrap\LoadConfiguration::class,
            \Phare\Foundation\Bootstrap\HandleExceptions::class,
            \Phare\Foundation\Bootstrap\RegisterProviders::class,
            \Phare\Foundation\Bootstrap\RegisterFacades::class,
        ]);

        Di::setDefault($this->app = $app);
    }

    /**
     * Sets the Dependency Injector.
     *
     * @return $this
     *
     * @see    Injectable::setDI
     */
    public function setDI(DiInterface $di)
    {
        $this->app = $di;

        return $this;
    }

    /**
     * Returns the internal Dependency Injector.
     *
     * @return DiInterface
     *
     * @see    Injectable::getDI
     */
    public function getDI()
    {
        if (!$this->app instanceof DiInterface) {
            return Di::getDefault();
        }

        return $this->app;
    }
}
