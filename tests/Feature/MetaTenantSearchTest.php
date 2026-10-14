<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Karnoweb\Meta\Exceptions\MetaException;
use Karnoweb\Meta\Facades\Meta;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\Fixtures\StaticTenantResolver;
use Karnoweb\Meta\Tests\Fixtures\User;
use Karnoweb\Meta\Tests\TestCase;

class MetaTenantSearchTest extends TestCase
{
    public function test_search_load_history_and_delete_stay_inside_the_tenant(): void
    {
        config([
            'meta.tenant.enabled' => true,
            'meta.tenant.resolver' => StaticTenantResolver::class,
        ]);

        $product = $this->product();
        $user = User::query()->create(['name' => 'Same id owner']);

        StaticTenantResolver::$key = 'tenant-a';
        $product->setMeta('color', 'red');
        $product->setMeta('price', '10.00', versioned: true, type: 'decimal');
        $user->setMeta('color', 'red');

        StaticTenantResolver::$key = 'tenant-b';
        $product->setMeta('color', 'blue');
        $product->setMeta('price', '20.00', versioned: true, type: 'decimal');

        StaticTenantResolver::$key = 'tenant-a';
        $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('color', '=', 'red')->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMeta('color', '=', 'blue')->get()));
        $this->assertSame([], $this->ids(User::query()->where('id', $product->id)->get()->filter(fn () => false)));
        $this->assertSame('red', $user->getMeta('color'));
        $this->assertCount(1, $product->metaHistory('price'));

        $loaded = Product::query()->withMeta(['color'])->get();
        $this->assertSame('red', $loaded->firstWhere('id', $product->id)->getMeta('color'));

        StaticTenantResolver::$key = 'tenant-b';
        $this->assertTrue(Meta::delete($product, 'color'));
        $this->assertFalse($product->hasMeta('color'));

        StaticTenantResolver::$key = 'tenant-a';
        $this->assertSame('red', $product->getMeta('color'));
        $this->assertSame('10.0000000000', $product->getMeta('price'));
    }

    public function test_invalid_resolver_never_runs_an_unscoped_query(): void
    {
        config([
            'meta.tenant.enabled' => true,
            'meta.tenant.resolver' => StaticTenantResolver::class,
        ]);
        $product = $this->product();
        StaticTenantResolver::$key = 'tenant-a';
        $product->setMeta('color', 'red');

        StaticTenantResolver::$key = '';

        try {
            Product::query()->whereMeta('color', '=', 'red')->get();
            $this->fail('Invalid tenant must fail closed.');
        } catch (MetaException) {
            $this->assertSame(1, \Illuminate\Support\Facades\DB::table('metas')->count());
        }
    }
}
