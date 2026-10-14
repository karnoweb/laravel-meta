<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Karnoweb\Meta\Facades\Meta;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\Fixtures\StaticBranchResolver;
use Karnoweb\Meta\Tests\TestCase;

class MetaEagerLoadingTest extends TestCase
{
    public function test_unversioned_with_meta_uses_two_queries_and_no_follow_up_reads(): void
    {
        $products = $this->seedProducts(100, false);

        $loaded = null;
        $duringLoad = $this->queries(function () use (&$loaded): void {
            $loaded = Product::query()->withMeta(['color'])->orderBy('id')->get();
        });

        $this->assertLessThanOrEqual(2, $duringLoad);
        $this->assertCount(100, $loaded);

        $duringRead = $this->queries(function () use ($loaded): void {
            foreach ($loaded as $product) {
                $product->getMeta('color');
            }
        });

        $this->assertSame(0, $duringRead);
        $this->assertSame('color-1', $loaded->first()->getMeta('color'));
        $this->assertSame('color-100', $loaded->last()->getMeta('color'));
        $this->assertSame($products[0]->id, $loaded->first()->id);
    }

    public function test_mixed_with_meta_uses_at_most_three_queries(): void
    {
        $this->seedProducts(100, true);

        $loaded = null;
        $duringLoad = $this->queries(function () use (&$loaded): void {
            $loaded = Product::query()->withMeta(['color', 'price'])->orderBy('id')->get();
        });

        $this->assertLessThanOrEqual(3, $duringLoad);

        $duringRead = $this->queries(function () use ($loaded): void {
            foreach ($loaded as $product) {
                $product->getMeta('color');
                $product->getMeta('price');
            }
        });

        $this->assertSame(0, $duringRead);
        $this->assertSame('10.0000000000', $loaded->first()->getMeta('price'));
        $this->assertSame('109.0000000000', $loaded->last()->getMeta('price'));
    }

    public function test_facade_load_is_bounded_and_empty_collection_runs_no_query(): void
    {
        $this->seedProducts(100, true);
        $products = Product::query()->orderBy('id')->get();

        $duringLoad = $this->queries(function () use ($products): void {
            Meta::load($products, ['color', 'price']);
        });

        $this->assertLessThanOrEqual(2, $duringLoad);
        $this->assertSame(0, $this->queries(fn () => Meta::load(collect(), ['color'])));
        $this->assertSame('color-1', $products->first()->getMeta('color'));
    }

    public function test_refresh_meta_sees_writes_and_future_versions(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2027-01-01 00:00:00', 'UTC'));
        $product = $this->product();
        $product->setMeta('color', 'red');
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');
        $product->setMetaAt('price', '200.00', '2027-03-01 00:00:00', type: 'decimal');

        $loaded = Product::query()->withMeta(['color', 'price'])->first();
        $this->assertSame('red', $loaded->getMeta('color'));
        $this->assertSame('100.0000000000', $loaded->getMeta('price'));

        Carbon::setTestNow(CarbonImmutable::parse('2027-03-02 00:00:00', 'UTC'));
        $this->assertSame('100.0000000000', $loaded->getMeta('price'));
        $loaded->refreshMeta();
        $this->assertSame('200.0000000000', $loaded->getMeta('price'));

        $loaded->setMeta('color', 'blue');
        $this->assertSame('blue', $loaded->getMeta('color'));
    }

    public function test_branch_switch_drops_the_loaded_snapshot(): void
    {
        config([
            'meta.branch.enabled' => true,
            'meta.branch.resolver' => StaticBranchResolver::class,
        ]);
        StaticBranchResolver::$key = 'branch-a';
        $product = $this->product();
        $product->setMeta('color', 'red');
        $loaded = Product::query()->withMeta(['color'])->first();
        $this->assertSame('red', $loaded->getMeta('color'));

        StaticBranchResolver::$key = 'branch-b';
        $loaded->setMeta('color', 'blue');
        $this->assertSame('blue', $loaded->getMeta('color'));

        StaticBranchResolver::$key = 'branch-a';
        $this->assertSame('red', $loaded->getMeta('color'));
    }

    public function test_get_meta_does_not_lazy_load_under_strict_mode(): void
    {
        Model::preventLazyLoading();
        $product = $this->product();
        $product->setMeta('color', 'red');
        $fresh = Product::query()->findOrFail($product->id);

        $this->assertSame('red', $fresh->getMeta('color'));
        Model::preventLazyLoading(false);
    }

    /**
     * @return list<Product>
     */
    private function seedProducts(int $count, bool $versionedPrice): array
    {
        $products = [];

        for ($i = 1; $i <= $count; $i++) {
            $product = $this->product('P'.$i);
            $product->setMeta('color', 'color-'.$i);

            if ($versionedPrice) {
                $product->setMeta('price', ($i + 8).'.00', versioned: true, type: 'decimal');
                $product->setMeta('price', ($i + 9).'.00', type: 'decimal');
            }

            $products[] = $product;
        }

        DB::flushQueryLog();

        return $products;
    }
}
