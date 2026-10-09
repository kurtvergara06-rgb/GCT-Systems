<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabaseIsolation;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        TestDatabaseIsolation::assertNoConfigurationCache(
            dirname(__DIR__)
        );

        $app = parent::createApplication();

        TestDatabaseIsolation::guard($app);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Backend feature tests should not depend on a pre-built Vite manifest.
        // Frontend asset compilation is verified separately by the CI build job.
        $this->withoutVite();
    }
}
