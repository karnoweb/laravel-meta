<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Karnoweb\Meta\Exceptions\MetaException;
use Karnoweb\Meta\Exceptions\MetaTypeException;
use Karnoweb\Meta\Exceptions\UnsavedModelException;
use Karnoweb\Meta\Facades\Meta;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\Fixtures\RestrictedProduct;
use Karnoweb\Meta\Tests\Fixtures\StaticBranchResolver;
use Karnoweb\Meta\Tests\Fixtures\UlidRecord;
use Karnoweb\Meta\Tests\Fixtures\User;
use Karnoweb\Meta\Tests\Fixtures\UuidRecord;
use Karnoweb\Meta\Tests\TestCase;

class MetaCrudTest extends TestCase
{
    public function test_normal_metadata_uses_one_row_and_zero_history_rows_after_many_updates(): void
    {
        $product = $this->product();

        $product->setMeta('color', 'red');
        $product->setMeta('color', 'blue');
        $returned = $product->setMeta('color', 'green');

        $this->assertSame($product, $returned);
        $this->assertSame('green', $product->getMeta('color'));
        $this->assertSame(1, DB::table('metas')->count());
        $this->assertSame(0, DB::table('meta_versions')->count());
        $this->assertSame('green', DB::table('metas')->value('value_string'));
        $this->assertFalse((bool) DB::table('metas')->value('versioned'));
        $this->assertFalse($product->isDirty());
    }

    public function test_missing_and_explicit_null_are_distinct(): void
    {
        $product = $this->product();
        $product->setMeta('note', null);
        $product->setMeta('label', false);
        $product->setMeta('empty', '');

        $this->assertTrue($product->hasMeta('note'));
        $this->assertTrue($product->hasMeta('label'));
        $this->assertTrue($product->hasMeta('empty'));
        $this->assertFalse($product->hasMeta('missing'));
        $this->assertNull($product->getMeta('note'));
        $this->assertNull($product->getMeta('note', 'fallback'));
        $this->assertSame('fallback', $product->getMeta('missing', 'fallback'));
        $this->assertFalse($product->getMeta('label'));
        $this->assertSame('', $product->getMeta('empty'));
        $this->assertSame('null', DB::table('metas')->where('key', 'note')->value('value_type'));
    }

    public function test_unsaved_owner_throws_without_creating_metadata(): void
    {
        $product = new Product(['name' => 'Draft']);

        try {
            $product->setMeta('color', 'red');
            $this->fail('An unsaved model must not accept meta.');
        } catch (UnsavedModelException) {
            $this->assertSame(0, DB::table('metas')->count());
        }

        $assigned = new UuidRecord(['name' => 'Pending', 'id' => (string) Str::uuid()]);

        $this->expectException(UnsavedModelException::class);
        $assigned->setMeta('color', 'red');
    }

    public function test_int_uuid_ulid_owner_ids_and_morph_map_are_supported(): void
    {
        Relation::enforceMorphMap([
            'product' => Product::class,
            'user' => User::class,
            'uuid' => UuidRecord::class,
            'ulid' => UlidRecord::class,
        ]);

        try {
            $product = $this->product('Numeric');
            $user = User::query()->create(['name' => 'User']);
            $uuid = new UuidRecord;
            $uuid->id = (string) Str::uuid();
            $uuid->name = 'UUID';
            $uuid->save();
            $ulid = new UlidRecord;
            $ulid->id = (string) Str::ulid();
            $ulid->name = 'ULID';
            $ulid->save();

            $product->setMeta('color', 'red');
            $user->setMeta('color', 'blue');
            $uuid->setMeta('color', 'green');
            $ulid->setMeta('color', 'black');

            $this->assertSame('red', $product->getMeta('color'));
            $this->assertSame('blue', $user->getMeta('color'));
            $this->assertSame('green', $uuid->getMeta('color'));
            $this->assertSame('black', $ulid->getMeta('color'));
            $this->assertSame('product', DB::table('metas')->where('metable_id', (string) $product->getKey())->value('metable_type'));
            $this->assertSame(36, strlen((string) DB::table('metas')->where('metable_type', 'uuid')->value('metable_id')));
            $this->assertSame(26, strlen((string) DB::table('metas')->where('metable_type', 'ulid')->value('metable_id')));
            $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('color', '=', 'red')->get()));
            $this->assertSame([], $this->ids(Product::query()->whereMeta('color', '=', 'blue')->get()));
        } finally {
            Relation::requireMorphMap(false);
            Relation::morphMap([], false);
        }
    }

    public function test_set_metas_is_atomic_and_rolls_back_on_error(): void
    {
        $product = $this->product();
        $product->setMeta('price', '10.00', versioned: true, type: 'decimal');
        $product->setMeta('existing', 'stay');

        try {
            $product->setMetas([
                'zeta' => 'last-key',
                'color' => 'green',
                'note' => str_repeat('ن', 513),
                'price' => '11.00',
            ]);
            $this->fail('Invalid batch member must roll back.');
        } catch (MetaTypeException) {
            $this->assertSame('stay', $product->getMeta('existing'));
            $this->assertFalse($product->hasMeta('color'));
            $this->assertFalse($product->hasMeta('zeta'));
            $this->assertSame('10.0000000000', $product->getMeta('price'));
            $this->assertSame(2, DB::table('metas')->count());
            $this->assertSame(1, DB::table('meta_versions')->count());
        }

        $product->setMetas([
            'price' => '11.00',
            'color' => 'blue',
        ]);

        $this->assertSame('blue', $product->getMeta('color'));
        $this->assertSame('11.00', $product->getMeta('price'));
        $this->assertSame(2, DB::table('meta_versions')->count());
        $this->assertTrue((bool) DB::table('metas')->where('key', 'price')->value('versioned'));
        $this->assertFalse((bool) DB::table('metas')->where('key', 'color')->value('versioned'));
    }

    public function test_branch_resolution_is_fail_closed_and_isolates_two_branches(): void
    {
        config([
            'meta.branch.enabled' => true,
            'meta.branch.resolver' => null,
        ]);

        $product = $this->product();

        try {
            $product->setMeta('color', 'red');
            $this->fail('A missing resolver must fail closed.');
        } catch (MetaException) {
            $this->assertSame(0, DB::table('metas')->count());
        }

        config(['meta.branch.resolver' => StaticBranchResolver::class]);
        StaticBranchResolver::$key = '';

        try {
            $product->setMeta('color', 'red');
            $this->fail('An empty branch key must fail closed.');
        } catch (MetaException) {
            $this->assertSame(0, DB::table('metas')->count());
        }

        StaticBranchResolver::$key = 'branch-a';
        $product->setMeta('color', 'red');
        $product->setMeta('price', '10.00', versioned: true, type: 'decimal');

        StaticBranchResolver::$key = 'branch-b';
        $this->assertFalse($product->hasMeta('color'));
        $this->assertSame('blue', $product->getMeta('color', 'blue'));
        $product->setMeta('color', 'blue');
        $product->setMeta('price', '20.00', versioned: true, type: 'decimal');

        StaticBranchResolver::$key = 'branch-a';
        $this->assertSame('red', $product->getMeta('color'));
        $this->assertSame('10.0000000000', $product->getMeta('price'));
        $this->assertCount(1, $product->metaHistory('price'));

        StaticBranchResolver::$key = 'branch-b';
        $this->assertSame('blue', $product->getMeta('color'));
        $this->assertSame('20.0000000000', $product->getMeta('price'));
        $this->assertTrue($product->deleteMeta('color'));
        $this->assertFalse($product->hasMeta('color'));

        StaticBranchResolver::$key = 'branch-a';
        $this->assertTrue($product->hasMeta('color'));
        $this->assertSame('red', $product->getMeta('color'));
        $this->assertSame(1, DB::table('metas')->where('key', 'color')->count());
        $this->assertSame('branch-a', DB::table('metas')->where('key', 'color')->value('branch_key'));
    }

    public function test_facade_and_trait_share_same_behavior(): void
    {
        $product = $this->product();

        Meta::set($product, 'color', 'blue');
        $this->assertSame('blue', $product->getMeta('color'));

        $product->setMeta('rating', 4);
        $this->assertSame(4, Meta::get($product, 'rating'));
        $this->assertTrue(Meta::has($product, 'color'));

        Meta::set($product, 'price', '100.00', versioned: true, type: 'decimal');
        Meta::setAt($product, 'price', '170.00', '2027-02-01 00:00:00', type: 'decimal');

        $this->assertSame('100.0000000000', $product->getMeta('price'));
        $this->assertSame('170.0000000000', $product->getMetaAt('price', '2027-02-01 00:00:00'));
        $this->assertCount(2, Meta::history($product, 'price'));
        $this->assertTrue(Meta::delete($product, 'color'));
        $this->assertFalse($product->hasMeta('color'));
        $this->assertFalse($product->deleteMeta('color'));
    }

    public function test_allowlist_rejects_unknown_keys(): void
    {
        $product = RestrictedProduct::query()->create(['name' => 'Limited']);
        $product->setMeta('color', 'red');

        $this->expectException(MetaException::class);
        $product->setMeta('weight', 1);
    }
}
