<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Meta\Tests\Fixtures\Product;

class DatabaseCompatibilityTest extends IntegrationTestCase
{
    public function test_schema_typed_query_versioning_and_explain(): void
    {
        $this->assertTrue(Schema::hasTable('metas'));
        $this->assertTrue(Schema::hasTable('meta_versions'));

        $indexes = collect(Schema::getIndexes('metas'))->pluck('name')->all();
        $this->assertContains('metas_owner_key_unique', $indexes);

        $match = $this->product('match');
        $other = $this->product('other');
        $match->setMeta('color', 'قرمز');
        $match->setMeta('count', 10);
        $match->setMeta('price', '10.20', versioned: true, type: 'decimal');
        $match->setMeta('price', '10.50', type: 'decimal');
        $match->setMeta('tags', ['vip']);
        $other->setMeta('count', 3);

        $this->assertSame([$match->id], $this->ids(Product::query()->whereMeta('color', '=', 'قرمز')->get()));
        $this->assertSame([$match->id], $this->ids(Product::query()->whereMeta('count', '>=', 10)->get()));
        $this->assertSame([$match->id], $this->ids(Product::query()->whereMeta('price', '=', '10.50', type: 'decimal')->get()));
        $this->assertSame([$match->id], $this->ids(Product::query()->whereMetaJsonContains('tags', 'vip')->get()));

        $sql = Product::query()->whereMeta('count', '>=', 10)->toSql();
        $explain = DB::select('explain '.$sql, Product::query()->whereMeta('count', '>=', 10)->getBindings());
        $this->assertNotEmpty($explain);
    }
}
