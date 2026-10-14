<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Meta\Facades\Meta;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\Fixtures\SoftProduct;
use Karnoweb\Meta\Tests\Fixtures\StaticBranchResolver;
use Karnoweb\Meta\Tests\TestCase;

class MetaLifecycleTest extends TestCase
{
    public function test_soft_delete_keeps_meta_and_force_delete_purges_only_when_enabled(): void
    {
        $soft = SoftProduct::query()->create(['name' => 'Soft']);
        $soft->setMeta('color', 'red');
        $soft->setMeta('price', '10.00', versioned: true, type: 'decimal');
        $soft->delete();

        $this->assertSame(2, DB::table('metas')->count());
        $this->assertNotNull(SoftProduct::withTrashed()->find($soft->id));

        $soft->forceDelete();
        $this->assertSame(2, DB::table('metas')->count());

        config(['meta.purge_on_force_delete' => true]);
        $second = SoftProduct::query()->create(['name' => 'Second']);
        $second->setMeta('color', 'blue');
        $second->setMeta('price', '12.00', versioned: true, type: 'decimal');
        $second->delete();
        $this->assertTrue(DB::table('metas')->where('metable_id', (string) $second->id)->exists());
        $second->forceDelete();
        $this->assertFalse(DB::table('metas')->where('metable_id', (string) $second->id)->exists());
        $this->assertSame(0, DB::table('meta_versions')->where('meta_id', '>', 0)->whereNotIn('meta_id', DB::table('metas')->pluck('id'))->count());
    }

    public function test_hard_delete_purges_only_when_enabled_and_bulk_delete_does_not(): void
    {
        config(['meta.purge_on_force_delete' => true]);
        $product = $this->product();
        $product->setMeta('color', 'red');
        $product->delete();
        $this->assertSame(0, DB::table('metas')->count());

        config(['meta.purge_on_force_delete' => false]);
        $kept = $this->product('Kept');
        $other = $this->product('Other');
        $kept->setMeta('color', 'green');
        $other->setMeta('color', 'blue');
        Product::query()->whereKey($other->id)->delete();
        $this->assertSame('blue', DB::table('metas')->where('metable_id', (string) $other->id)->value('value_string'));

        $this->assertSame(1, Meta::purge($other));
        $this->assertSame(0, DB::table('metas')->where('metable_id', (string) $other->id)->count());
        $this->assertSame('green', $kept->getMeta('color'));
    }

    public function test_purge_is_limited_to_the_active_branch_and_owner_connection(): void
    {
        config([
            'meta.branch.enabled' => true,
            'meta.branch.resolver' => StaticBranchResolver::class,
        ]);
        $product = $this->product();
        StaticBranchResolver::$key = 'branch-a';
        $product->setMeta('color', 'red');
        StaticBranchResolver::$key = 'branch-b';
        $product->setMeta('color', 'blue');

        StaticBranchResolver::$key = 'branch-a';
        $this->assertSame(1, Meta::purge($product));
        $this->assertFalse($product->hasMeta('color'));

        StaticBranchResolver::$key = 'branch-b';
        $this->assertSame('blue', $product->getMeta('color'));
        $this->assertTrue(Schema::hasTable('metas'));
    }

    public function test_meta_is_written_on_the_owner_connection(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-meta-'.uniqid('', true).'.sqlite';
        touch($path);

        try {
            config()->set('database.connections.alt', [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
            DB::purge('alt');

            Schema::connection('alt')->create('products', function ($table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });

            app('migrator')->usingConnection('alt', function (): void {
                $migrator = app('migrator');

                if (! $migrator->repositoryExists()) {
                    $migrator->getRepository()->createRepository();
                }

                $migrator->run([dirname(__DIR__, 2).'/database/migrations']);
            });

            $product = new Product;
            $product->setConnection('alt');
            $product->name = 'Alt';
            $product->save();
            $product->setMeta('color', 'red');

            $this->assertSame(0, DB::table('metas')->count());
            $this->assertSame('red', DB::connection('alt')->table('metas')->value('value_string'));
            $this->assertSame('red', $product->getMeta('color'));
        } finally {
            DB::disconnect('alt');
            @unlink($path);
        }
    }
}
