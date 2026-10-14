<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Karnoweb\Meta\MetaServiceProvider;
use Karnoweb\Meta\Tests\TestCase;

class InstallationTest extends TestCase
{
    public function test_provider_auto_discovers_and_loads_fixed_migration_without_publish(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

        $this->assertContains(
            MetaServiceProvider::class,
            $composer['extra']['laravel']['providers'],
        );
        $this->assertTrue($this->app->providerIsLoaded(MetaServiceProvider::class));
        $this->assertTrue(Schema::hasTable('metas'));
        $this->assertTrue(Schema::hasTable('meta_versions'));
        $this->assertFileExists(dirname(__DIR__, 2).'/database/migrations/2020_01_01_090000_create_meta_tables.php');

        $config = ServiceProvider::pathsToPublish(MetaServiceProvider::class, 'meta-config');
        $migrations = ServiceProvider::pathsToPublish(MetaServiceProvider::class, 'meta-migrations');

        $this->assertNotEmpty($config);
        $this->assertSame([], $migrations);

        foreach (array_keys($config) as $source) {
            $this->assertStringNotContainsString('migrations', str_replace('\\', '/', (string) $source));
        }

        $indexes = collect(Schema::getIndexes('metas'))->pluck('name')->all();
        $this->assertContains('metas_owner_key_unique', $indexes);
        $this->assertContains('metas_owner_index', $indexes);
        $this->assertContains('metas_string_lookup_index', $indexes);
        $this->assertContains('metas_integer_lookup_index', $indexes);
        $this->assertContains('metas_decimal_lookup_index', $indexes);
        $this->assertContains(
            'meta_versions_resolution_index',
            collect(Schema::getIndexes('meta_versions'))->pluck('name')->all(),
        );
    }

    public function test_config_cache_does_not_need_config_publication(): void
    {
        $this->assertFalse(config('meta.tenant.enabled'));
        $this->assertNull(config('meta.tenant.resolver'));
        $this->assertSame(120, config('meta.max_key_length'));
        $this->assertSame(512, config('meta.max_string_length'));
        $this->assertSame(1048576, config('meta.max_json_bytes'));
        $this->assertFalse(config('meta.purge_on_force_delete'));
        $this->assertIsString(serialize(config('meta')));
        $this->assertFileDoesNotExist(config_path('meta.php'));

        $product = $this->product();
        $product->setMeta('color', 'red');

        $this->assertSame('red', $product->getMeta('color'));
        $this->assertArrayNotHasKey('color', $product->getAttributes());
    }
}
