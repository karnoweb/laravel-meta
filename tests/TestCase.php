<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Meta\Facades\Meta as MetaFacade;
use Karnoweb\Meta\MetaServiceProvider;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\Fixtures\StaticBranchResolver;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow();
        StaticBranchResolver::$key = 'branch-a';
        DB::enableQueryLog();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        StaticBranchResolver::$key = 'branch-a';

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [MetaServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Meta' => MetaFacade::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('uuid_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('ulid_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function product(string $name = 'Sample'): Product
    {
        return Product::query()->create(['name' => $name]);
    }

    /**
     * @param  list<int>  $expected
     * @return list<int>
     */
    protected function ids(iterable $models): array
    {
        $ids = [];

        foreach ($models as $model) {
            $ids[] = (int) $model->getKey();
        }

        sort($ids);

        return $ids;
    }

    protected function queries(callable $callback): int
    {
        DB::flushQueryLog();
        $callback();

        return count(DB::getQueryLog());
    }

    protected function forget(Model $model): void
    {
        $model->unsetRelations();
    }
}
