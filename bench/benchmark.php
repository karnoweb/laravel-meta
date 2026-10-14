<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Meta\MetaServiceProvider;
use Karnoweb\Meta\Tests\Fixtures\Product;

require dirname(__DIR__).'/vendor/autoload.php';

$size = (int) ($argv[1] ?? 10000);
$allowed = [10000, 100000, 1000000];
$reuse = in_array('--reuse', $argv, true);

if (! in_array($size, $allowed, true)) {
    fwrite(STDERR, "Size must be one of: 10000, 100000, 1000000.\n");
    exit(1);
}

if ($size > 10000 && ($argv[2] ?? '') !== '--confirm') {
    fwrite(STDERR, "Refusing to seed {$size} rows without --confirm.\n");
    exit(1);
}

$resultsDir = __DIR__.'/results';

if (! is_dir($resultsDir) && ! mkdir($resultsDir, 0777, true) && ! is_dir($resultsDir)) {
    fwrite(STDERR, "Cannot create {$resultsDir}.\n");
    exit(1);
}

$path = $resultsDir.'/bench.sqlite';

if (! $reuse) {
    @unlink($path);
    touch($path);
} elseif (! is_file($path)) {
    fwrite(STDERR, "No existing bench database at {$path}.\n");
    exit(1);
}

$container = new Container;
Container::setInstance($container);
Facade::setFacadeApplication($container);
$container->instance('config', new Repository([
    'meta' => require dirname(__DIR__).'/config/meta.php',
]));

$capsule = new Capsule($container);
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => $path,
    'prefix' => '',
    'foreign_key_constraints' => true,
], 'bench');
$capsule->getDatabaseManager()->setDefaultConnection('bench');
$capsule->setAsGlobal();
$capsule->bootEloquent();
$container->instance('db', $capsule->getDatabaseManager());
$container->singleton('db.schema', fn () => $capsule->getConnection('bench')->getSchemaBuilder());
(new MetaServiceProvider($container))->register();

$counter = new stdClass;
$counter->queries = 0;
$GLOBALS['meta_bench_counter'] = $counter;
$connection = $capsule->getConnection('bench');
$connection->beforeExecuting(function () use ($counter): void {
    $counter->queries++;
});

if (! $reuse) {
    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $migration = require dirname(__DIR__).'/database/migrations/2020_01_01_090000_create_meta_tables.php';
    $migration->up();
}

$started = hrtime(true);
$versioned = 0;

if (! $reuse) {
    $now = '2026-10-14 08:00:00';

    for ($offset = 0; $offset < $size; $offset += 500) {
        $rows = [];
        $limit = min(500, $size - $offset);

        for ($i = 1; $i <= $limit; $i++) {
            $rows[] = [
                'name' => 'P'.($offset + $i),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $connection->table('products')->insert($rows);
    }

    Product::query()->orderBy('id')->chunkById(200, function ($products) use (&$versioned): void {
        foreach ($products as $product) {
            $n = (int) $product->id;
            $product->setMeta('color', $n % 2 === 0 ? 'red' : 'blue');
            $product->setMeta('count', $n % 50);
            $product->setMeta('price', number_format($n / 100, 2, '.', ''), type: 'decimal');
            $product->setMeta('started_at', '2027-01-01 00:00:00', type: 'datetime');

            if ($n % 10 === 0) {
                $product->setMeta('price', number_format(($n + 1) / 100, 2, '.', ''), versioned: true, type: 'decimal');
                $versioned++;
            }
        }
    });
} else {
    $versioned = (int) $connection->table('metas')->where('key', 'price')->where('versioned', 1)->count();
}

$writeMs = $reuse ? null : (hrtime(true) - $started) / 1_000_000;

/**
 * @param  callable(): mixed  $callback
 * @return array{median_ms: float, p95_ms: float, queries: int}
 */
function measure(callable $callback, int $repeats = 7): array
{
    $counter = $GLOBALS['meta_bench_counter'];

    $callback();
    $samples = [];
    $queries = 0;

    for ($i = 0; $i < $repeats; $i++) {
        $before = $counter->queries;
        $mark = hrtime(true);
        $callback();
        $samples[] = (hrtime(true) - $mark) / 1_000_000;
        $queries = $counter->queries - $before;
    }

    sort($samples);
    $mid = intdiv(count($samples), 2);
    $median = count($samples) % 2 === 0
        ? ($samples[$mid - 1] + $samples[$mid]) / 2
        : $samples[$mid];
    $p95index = (int) ceil(count($samples) * 0.95) - 1;

    return [
        'median_ms' => round($median, 3),
        'p95_ms' => round($samples[max(0, $p95index)], 3),
        'queries' => $queries,
    ];
}

$report = [
    'ran_at' => gmdate('c'),
    'php' => PHP_VERSION,
    'db' => 'sqlite '.$connection->scalar('select sqlite_version()'),
    'rows' => $size,
    'versioned_price_roots' => $versioned,
    'meta_roots' => (int) $connection->table('metas')->count(),
    'meta_versions' => (int) $connection->table('meta_versions')->count(),
    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
    'bulk_write_ms' => $writeMs === null ? null : round($writeMs, 3),
    'indexes' => [
        'metas' => array_column(Schema::getIndexes('metas'), 'name'),
        'meta_versions' => array_column(Schema::getIndexes('meta_versions'), 'name'),
    ],
    'operations' => [
        'get_one_unversioned' => measure(function (): void {
            Product::query()->orderBy('id')->first()?->getMeta('color');
        }),
        'where_color' => measure(fn () => Product::query()->whereMeta('color', '=', 'red')->count()),
        'where_count' => measure(fn () => Product::query()->whereMeta('count', '>=', 10)->count()),
        'where_price' => measure(fn () => Product::query()->whereMeta('price', '>=', '1.00', type: 'decimal')->count()),
        'where_started_at' => measure(fn () => Product::query()->whereMeta('started_at', '=', '2027-01-01 00:00:00')->count()),
        'where_versioned_price' => measure(fn () => Product::query()->whereMeta('price', '>=', '10.00', type: 'decimal')->count()),
        'load_100' => measure(function (): void {
            $models = Product::query()->orderBy('id')->limit(100)->get();
            $models->load([]);
            foreach ($models as $model) {
                $model->getMeta('color');
                $model->getMeta('price');
            }
        }),
        'with_meta_100' => measure(function (): void {
            $models = Product::query()->withMeta(['color', 'price'])->orderBy('id')->limit(100)->get();
            foreach ($models as $model) {
                $model->getMeta('color');
                $model->getMeta('price');
            }
        }),
        'with_meta_1000' => measure(function (): void {
            Product::query()->withMeta(['color', 'price'])->orderBy('id')->limit(1000)->get();
        }),
    ],
];

$report['peak_memory_mb'] = round(memory_get_peak_usage(true) / 1048576, 2);
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($resultsDir.'/latest.json', $json.PHP_EOL);
echo $json.PHP_EOL;
