<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep test runs out of the real audit log.
        config(['logging.channels.audit.path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gasstation_audit_test.log']);
    }
}
