<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Karnoweb\Meta\Exceptions\MetaException;
use Karnoweb\Meta\Exceptions\MetaQueryException;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\TestCase;

class MetaSqlSafetyTest extends TestCase
{
    public function test_keys_operators_and_like_patterns_are_bound(): void
    {
        $product = $this->product();
        $product->setMeta('color', 'red');
        $before = DB::table('products')->count();

        $this->assertSame([], $this->ids(Product::query()->whereMeta("color' OR '1'='1", '=', 'red')->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMetaLike('color', "%' OR 1=1 --")->get()));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('products'));
        $this->assertSame($before, DB::table('products')->count());
        $this->assertSame('red', $product->getMeta('color'));

        try {
            Product::query()->whereMeta('color', 'or 1=1; --', 'red')->get();
            $this->fail('Unknown operators must be rejected.');
        } catch (MetaQueryException) {
            $this->addToAssertionCount(1);
        }

        try {
            Product::query()->whereMeta("bad\x00key", '=', 'red')->get();
            $this->fail('Control characters in keys must be rejected.');
        } catch (MetaException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_morph_types_with_the_same_id_do_not_leak(): void
    {
        $product = $this->product('Shared');
        $userClass = \Karnoweb\Meta\Tests\Fixtures\User::query()->create(['name' => 'Shared']);
        $product->setMeta('color', 'red');
        $userClass->setMeta('color', 'blue');

        $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('color', '=', 'red')->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMeta('color', '=', 'blue')->get()));
        $this->assertSame('blue', $userClass->getMeta('color'));
        $this->assertSame('red', $product->getMeta('color'));
    }
}
