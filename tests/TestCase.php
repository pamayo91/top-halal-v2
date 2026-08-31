<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to boot PHPUnit against a cached non-testing configuration.
     *
     * A cached preproduction config can otherwise override phpunit.xml before
     * database-migrating test traits run.
     */
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        if ($app->environment() !== 'testing' || $app['config']->get('database.default') !== 'sqlite') {
            throw new \RuntimeException('Refusing to run tests without the SQLite testing configuration. Clear cached configuration first.');
        }

        return $app;
    }
}
