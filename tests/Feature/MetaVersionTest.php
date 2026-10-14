<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Karnoweb\Meta\Exceptions\MetaVersionException;
use Karnoweb\Meta\Tests\TestCase;

class MetaVersionTest extends TestCase
{
    public function test_versioning_is_opt_in_and_inherited_on_subsequent_writes(): void
    {
        $product = $this->product();
        $product->setMeta('price', '120.5000000000', versioned: true, type: 'decimal');
        $product->setMeta('price', '130.00', type: 'decimal');

        $this->assertSame('130.0000000000', $product->getMeta('price'));
        $this->assertSame(1, DB::table('metas')->count());
        $this->assertSame(2, DB::table('meta_versions')->count());
        $this->assertNull(DB::table('metas')->value('value_decimal'));
        $this->assertTrue((bool) DB::table('metas')->value('versioned'));
        $this->assertSame(
            ['120.5000000000', '130.0000000000'],
            $product->metaHistory('price')->map->value()->all(),
        );
    }

    public function test_explicit_disabling_of_versioned_key_is_rejected(): void
    {
        $product = $this->product();
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');
        $product->setMeta('price', '110.00', type: 'decimal');

        try {
            $product->setMeta('price', '90.00', versioned: false, type: 'decimal');
            $this->fail('versioned:false must be rejected.');
        } catch (MetaVersionException) {
            $this->assertSame('110.0000000000', $product->getMeta('price'));
            $this->assertSame(1, DB::table('metas')->count());
            $this->assertSame(2, DB::table('meta_versions')->count());
            $this->assertTrue((bool) DB::table('metas')->value('versioned'));
        }
    }

    public function test_promotion_preserves_original_value_in_history(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2027-04-01 12:00:00', 'UTC'));
        $product = $this->product();
        $product->setMeta('status', 'draft');

        try {
            $product->setMetaAt('status', 'archived', '2027-03-01 00:00:00');
            $this->fail('Backdating a promotion must not fabricate history.');
        } catch (MetaVersionException) {
            $this->assertSame('draft', $product->getMeta('status'));
            $this->assertSame(0, DB::table('meta_versions')->count());
            $this->assertFalse((bool) DB::table('metas')->value('versioned'));
        }

        $product->setMeta('status', 'active', versioned: true);

        $history = $product->metaHistory('status');

        $this->assertSame(['draft', 'active'], $history->map->value()->all());
        $this->assertSame('active', $product->getMeta('status'));
        $this->assertSame(1, DB::table('metas')->count());
        $this->assertSame(2, DB::table('meta_versions')->count());
        $this->assertNull(DB::table('metas')->value('value_string'));
        $this->assertSame('2027-04-01 12:00:00.000000', $history[0]->effective_at);
        $this->assertTrue($history[1]->id > $history[0]->id);
    }

    public function test_future_version_becomes_current_when_time_advances_without_scheduler(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2027-01-31 00:00:00', 'UTC'));
        $product = $this->product();
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');
        $product->setMetaAt('price', '200.00', '2027-02-01 00:00:00', type: 'decimal');

        $this->assertSame('100.0000000000', $product->getMeta('price'));
        $this->assertTrue($product->hasMeta('price'));

        Carbon::setTestNow(CarbonImmutable::parse('2027-02-01 00:00:00', 'UTC'));

        $this->assertSame('200.0000000000', $product->getMeta('price'));
        $this->assertSame(2, DB::table('meta_versions')->count());
    }

    public function test_as_of_uses_effective_at_and_id_tie_breaker(): void
    {
        $product = $this->product();
        $at = '2026-03-01 00:00:00';
        $product->setMetaAt('price', '100.00', $at, type: 'decimal');
        $product->setMetaAt('price', '150.00', $at, type: 'decimal');

        $history = $product->metaHistory('price');

        $this->assertSame('150.0000000000', $product->getMeta('price'));
        $this->assertSame('150.0000000000', $product->getMetaAt('price', $at));
        $this->assertSame('100.0000000000', $history[0]->value());
        $this->assertTrue($history[1]->id > $history[0]->id);
        $this->assertSame($history[0]->effective_at, $history[1]->effective_at);
    }

    public function test_backdated_version_and_tombstone_semantics(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2027-06-01 00:00:00', 'UTC'));
        $product = $this->product();
        $product->setMetaAt('price', '100.00', '2027-01-01 00:00:00', type: 'decimal');
        $product->setMetaAt('price', '150.00', '2027-05-01 00:00:00', type: 'decimal');
        $product->setMetaAt('price', '110.00', '2027-03-01 00:00:00', type: 'decimal');

        $this->assertSame('100.0000000000', $product->getMetaAt('price', '2027-01-15 00:00:00'));
        $this->assertSame('110.0000000000', $product->getMetaAt('price', '2027-03-15 00:00:00'));
        $this->assertSame('150.0000000000', $product->getMetaAt('price', '2027-05-15 00:00:00'));
        $this->assertSame('150.0000000000', $product->getMeta('price'));

        $this->assertTrue($product->deleteMeta('price'));
        $this->assertFalse($product->hasMeta('price'));
        $this->assertSame('gone', $product->getMeta('price', 'gone'));
        $this->assertSame('150.0000000000', $product->getMetaAt('price', '2027-05-15 00:00:00'));
        $this->assertSame('gone', $product->getMetaAt('price', '2027-06-01 00:00:00', 'gone'));
        $this->assertTrue($product->metaHistory('price')->last()->is_deleted);
        $this->assertFalse($product->deleteMeta('price'));
        $this->assertSame(4, DB::table('meta_versions')->count());
    }

    public function test_delete_unversioned_is_physical_and_versioned_is_logical(): void
    {
        $product = $this->product();
        $product->setMeta('color', 'red');
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');

        $this->assertTrue($product->deleteMeta('color'));
        $this->assertFalse($product->deleteMeta('color'));
        $this->assertSame(0, DB::table('metas')->where('key', 'color')->count());
        $this->assertFalse($product->hasMeta('color'));

        $this->assertTrue($product->deleteMeta('price'));
        $this->assertSame(1, DB::table('metas')->where('key', 'price')->count());
        $this->assertSame(2, DB::table('meta_versions')->count());
        $this->assertTrue((bool) DB::table('meta_versions')->orderByDesc('id')->value('is_deleted'));
        $this->assertFalse($product->hasMeta('price'));
        $this->assertCount(2, $product->metaHistory('price'));
    }

    public function test_unversioned_get_meta_at_is_rejected(): void
    {
        $product = $this->product();
        $product->setMeta('color', 'red');

        $this->expectException(MetaVersionException::class);
        $product->getMetaAt('color', '2027-01-01 00:00:00');
    }

    public function test_relative_instants_are_rejected(): void
    {
        $product = $this->product();

        $this->expectException(\Karnoweb\Meta\Exceptions\MetaException::class);
        $product->setMetaAt('price', '10.00', '+2 weeks', type: 'decimal');
    }
}
