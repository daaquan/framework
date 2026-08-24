<?php

namespace Phare\Testing;

use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;
use Phare\Foundation\AbstractApplication as Application;
use Phare\Foundation\Bootstrap\HandleExceptions;
use Phare\Foundation\Bootstrap\LoadConfiguration;
use Phare\Foundation\Bootstrap\LoadEnvironmentVariables;
use Phare\Foundation\Bootstrap\RegisterFacades;
use Phare\Foundation\Bootstrap\RegisterProviders;
use Phare\Foundation\Testing\Concerns\MakesHttpRequests;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use MakesHttpRequests;

    protected ?Application $app = null;

    abstract public function createApplication(): Application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApplication();
    }

    protected function tearDown(): void
    {
        // In unit tests, HandleExceptions bootstrapper does not register handlers,
        // so avoid restoring handlers here to prevent popping PHPUnit/Pest handlers.
        Di::reset();

        parent::tearDown();
    }

    public function setUpApplication(): void
    {
        if (!defined('APP_RUNNING_UNIT_TEST')) {
            define('APP_RUNNING_UNIT_TEST', true);
        }

        Di::reset();

        $app = $this->createApplication();
        $app->bootstrapWith([
            LoadEnvironmentVariables::class,
            LoadConfiguration::class,
            HandleExceptions::class,
            RegisterProviders::class,
            RegisterFacades::class,
        ]);

        Di::setDefault(($this->app = $app)->phalconDi());
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
