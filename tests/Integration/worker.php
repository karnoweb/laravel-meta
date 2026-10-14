<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use Karnoweb\Meta\MetaServiceProvider;
use Karnoweb\Meta\Tests\Fixtures\Product;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$driver = (string) getenv('META_DB_DRIVER');
$host = getenv('META_DB_HOST') ?: '127.0.0.1';
$database = getenv('META_DB_DATABASE') ?: 'meta_test';
$username = getenv('META_DB_USERNAME') ?: ($driver === 'pgsql' ? 'postgres' : 'root');
$password = getenv('META_DB_PASSWORD') ?: 'root';
$port = getenv('META_DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');

$connection = [
    'driver' => $driver,
    'host' => $host,
    'port' => $port,
    'database' => $database,
    'username' => $username,
    'password' => $password,
    'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
    'prefix' => '',
];

$container = new Container;
Container::setInstance($container);
Facade::setFacadeApplication($container);
$container->instance('config', new Repository([
    'meta' => require dirname(__DIR__, 2).'/config/meta.php',
]));

$capsule = new Capsule($container);
$capsule->addConnection($connection, 'testing');
$capsule->getDatabaseManager()->setDefaultConnection('testing');
$capsule->setAsGlobal();
$capsule->bootEloquent();
$container->instance('db', $capsule->getDatabaseManager());

(new MetaServiceProvider($container))->register();

$ownerId = (int) ($argv[1] ?? 0);
$key = (string) ($argv[2] ?? '');
$value = (string) ($argv[3] ?? '');
$type = ($argv[4] ?? '') !== '' ? (string) $argv[4] : null;
$versioned = ($argv[5] ?? '') === '1' ? true : null;

$product = Product::on('testing')->findOrFail($ownerId);
$product->setMeta($key, $value, versioned: $versioned, type: $type);

echo "ok\n";
