<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Integration;

use Karnoweb\Meta\Tests\TestCase;
use RuntimeException;

abstract class IntegrationTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $driver = getenv('META_DB_DRIVER') ?: '';

        if (getenv('META_INTEGRATION') !== '1' || $driver === '' || $driver === 'sqlite') {
            parent::defineEnvironment($app);

            return;
        }

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->connectionConfig($driver));
    }

    /**
     * @return array<string, mixed>
     */
    protected function connectionConfig(string $driver): array
    {
        return match ($driver) {
            'mysql' => [
                'driver' => 'mysql',
                'host' => getenv('META_DB_HOST') ?: '127.0.0.1',
                'port' => getenv('META_DB_PORT') ?: '3306',
                'database' => getenv('META_DB_DATABASE') ?: 'meta_test',
                'username' => getenv('META_DB_USERNAME') ?: 'root',
                'password' => getenv('META_DB_PASSWORD') ?: 'root',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => getenv('META_DB_HOST') ?: '127.0.0.1',
                'port' => getenv('META_DB_PORT') ?: '5432',
                'database' => getenv('META_DB_DATABASE') ?: 'meta_test',
                'username' => getenv('META_DB_USERNAME') ?: 'postgres',
                'password' => getenv('META_DB_PASSWORD') ?: 'root',
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
            ],
            default => throw new RuntimeException("Unsupported META_DB_DRIVER [{$driver}]."),
        };
    }
}
