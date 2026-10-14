<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\TestCase;

class MetaVersionSearchTest extends TestCase
{
    public function test_search_uses_current_version_not_history(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2027-01-01 00:00:00', 'UTC'));
        $product = $this->product();
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');

        Carbon::setTestNow(CarbonImmutable::parse('2027-01-15 00:00:00', 'UTC'));
        $product->setMeta('price', '200.00', type: 'decimal');
        $product->setMetaAt('price', '300.00', '2027-02-01 00:00:00', type: 'decimal');
        $plain = $this->product('plain');
        $plain->setMeta('price', '100.00', type: 'decimal');

        $this->assertSame([$plain->id], $this->ids(Product::query()->whereMeta('price', '=', '100.00', type: 'decimal')->get()));
        $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('price', '=', '200.00', type: 'decimal')->get()));
        $this->assertSame([$product->id], $this->ids(Product::query()->whereMetaAt('price', '=', '100.00', '2027-01-10 00:00:00', type: 'decimal')->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMetaAt('price', '=', '100.00', '2027-01-10 00:00:00', type: 'decimal')->where('name', 'plain')->get()));

        Carbon::setTestNow(CarbonImmutable::parse('2027-02-02 00:00:00', 'UTC'));
        $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('price', '=', '300.00', type: 'decimal')->get()));
    }

    public function test_future_only_tombstone_and_tie_are_respected(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2027-01-01 00:00:00', 'UTC'));
        $future = $this->product('future');
        $future->setMetaAt('price', '300.00', '2027-05-01 00:00:00', type: 'decimal');

        $tomb = $this->product('tomb');
        $tomb->setMeta('price', '200.00', versioned: true, type: 'decimal');
        $tomb->deleteMeta('price');
        $tomb->setMetaAt('price', '120.00', '2027-06-01 00:00:00', type: 'decimal');

        $tie = $this->product('tie');
        $tie->setMetaAt('label', 'first', '2027-01-01 00:00:00');
        $tie->setMetaAt('label', 'second', '2027-01-01 00:00:00');

        $this->assertSame([], $this->ids(Product::query()->whereHasMeta('price')->where('name', 'future')->get()));
        $this->assertSame([$future->id], $this->ids(Product::query()->whereDoesntHaveMeta('price')->where('name', 'future')->get()));
        $this->assertFalse($tomb->hasMeta('price'));
        $this->assertSame([], $this->ids(Product::query()->whereMeta('price', '=', '200.00', type: 'decimal')->get()));

        Carbon::setTestNow(CarbonImmutable::parse('2027-06-02 00:00:00', 'UTC'));
        $this->assertSame([$tomb->id], $this->ids(Product::query()->whereMeta('price', '=', '120.00', type: 'decimal')->get()));
        $this->assertSame([$tie->id], $this->ids(Product::query()->whereMeta('label', '=', 'second')->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMeta('label', '=', 'first')->get()));
    }

    public function test_where_meta_at_ignores_unversioned_keys(): void
    {
        $product = $this->product();
        $product->setMeta('color', 'red');

        $this->assertSame([], $this->ids(Product::query()->whereMetaAt('color', '=', 'red', '2026-01-01 00:00:00')->get()));
    }

    public function test_versioned_type_change_uses_only_the_latest_version(): void
    {
        $product = $this->product();
        $product->setMeta('code', '100', versioned: true);
        $product->setMeta('code', 100);

        $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('code', '=', 100)->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMeta('code', '=', '100')->get()));
    }
}
