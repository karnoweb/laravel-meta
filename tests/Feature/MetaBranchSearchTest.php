<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Karnoweb\Meta\Exceptions\MetaException;
use Karnoweb\Meta\Facades\Meta;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\Fixtures\StaticBranchResolver;
use Karnoweb\Meta\Tests\Fixtures\User;
use Karnoweb\Meta\Tests\TestCase;

class MetaBranchSearchTest extends TestCase
{
    public function test_search_load_history_and_delete_stay_inside_the_branch(): void
    {
        config([
            'meta.branch.enabled' => true,
            'meta.branch.resolver' => StaticBranchResolver::class,
        ]);

        $product = $this->product();
        $user = User::query()->create(['name' => 'Same id owner']);

        StaticBranchResolver::$key = 'branch-a';
        $product->setMeta('color', 'red');
        $product->setMeta('price', '10.00', versioned: true, type: 'decimal');
        $user->setMeta('color', 'red');

        StaticBranchResolver::$key = 'branch-b';
        $product->setMeta('color', 'blue');
        $product->setMeta('price', '20.00', versioned: true, type: 'decimal');

        StaticBranchResolver::$key = 'branch-a';
        $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('color', '=', 'red')->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMeta('color', '=', 'blue')->get()));
        $this->assertSame([], $this->ids(User::query()->where('id', $product->id)->get()->filter(fn () => false)));
        $this->assertSame('red', $user->getMeta('color'));
        $this->assertCount(1, $product->metaHistory('price'));

        $loaded = Product::query()->withMeta(['color'])->get();
        $this->assertSame('red', $loaded->firstWhere('id', $product->id)->getMeta('color'));

        StaticBranchResolver::$key = 'branch-b';
        $this->assertTrue(Meta::delete($product, 'color'));
        $this->assertFalse($product->hasMeta('color'));

        StaticBranchResolver::$key = 'branch-a';
        $this->assertSame('red', $product->getMeta('color'));
        $this->assertSame('10.0000000000', $product->getMeta('price'));
    }

    public function test_invalid_resolver_never_runs_an_unscoped_query(): void
    {
        config([
            'meta.branch.enabled' => true,
            'meta.branch.resolver' => StaticBranchResolver::class,
        ]);
        $product = $this->product();
        StaticBranchResolver::$key = 'branch-a';
        $product->setMeta('color', 'red');

        StaticBranchResolver::$key = '';

        try {
            Product::query()->whereMeta('color', '=', 'red')->get();
            $this->fail('Invalid branch must fail closed.');
        } catch (MetaException) {
            $this->assertSame(1, \Illuminate\Support\Facades\DB::table('metas')->count());
        }
    }
}
