<?php

namespace Tests;

use Phare\Foundation\AbstractApplication as Application;

class TestCase extends \Phare\Testing\TestCase
{
    public function createApplication(): Application
    {
        return require $_ENV['APP_BASE_PATH'] . '/bootstrap/app.php';
    }
}
