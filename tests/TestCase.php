<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Backend feature tests should not depend on a pre-built Vite manifest.
        // Frontend asset compilation is verified separately by the CI build job.
        $this->withoutVite();
    }
}
