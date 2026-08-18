<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Prevent database-refreshing tests from ever using the application database.
     */
    public function createApplication(): Application
    {
        $application = parent::createApplication();
        $requestedEnvironment = (string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '');
        $connection = (string) $application['config']->get('database.default');
        $database = (string) $application['config']->get("database.connections.{$connection}.database");

        if ($requestedEnvironment === 'testing' && ($connection !== 'sqlite' || $database !== ':memory:')) {
            throw new RuntimeException(
                "Testing dihentikan: koneksi database harus sqlite :memory:, tetapi memakai {$connection} {$database}. "
                .'Jalankan php artisan config:clear sebelum test.'
            );
        }

        return $application;
    }
}
