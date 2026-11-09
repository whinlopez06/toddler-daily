<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $application = parent::createApplication();
        if ($application['config']->get('database.default') !== 'pgsql' || $application['config']->get('database.connections.pgsql.database') !== 'toddler_daily_test') {
            throw new \RuntimeException('Tests may only run against the dedicated toddler_daily_test PostgreSQL database.');
        }
        return $application;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost:4300');
    }
}
