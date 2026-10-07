<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.connections.mariadb');

        // Check before RefreshDatabase can run destructive migrations.
        if (! $app->environment('testing')
            || $app['config']->get('database.default') !== 'mariadb'
            || $connection['database'] !== 'db_test'
            || $connection['username'] !== 'db_test'
            || ! empty($connection['url'])) {
            throw new LogicException('Tests must use the isolated db_test database and user.');
        }

        return $app;
    }
}
