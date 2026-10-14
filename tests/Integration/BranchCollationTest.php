<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Karnoweb\Meta\Exceptions\MetaException;
use Karnoweb\Meta\Facades\Meta;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\Fixtures\StaticBranchResolver;

class BranchCollationTest extends IntegrationTestCase
{
    public function test_branch_keys_that_differ_only_by_case_stay_separate(): void
    {
        config([
            'meta.branch.enabled' => true,
            'meta.branch.resolver' => StaticBranchResolver::class,
        ]);

        $product = $this->product();
        StaticBranchResolver::$key = 'Branch-A';
        $product->setMeta('color', 'Red');
        $product->setMeta('price', '10.00', versioned: true, type: 'decimal');

        StaticBranchResolver::$key = 'branch-a';
        $product->setMeta('color', 'red');
        $product->setMeta('price', '11.00', versioned: true, type: 'decimal');

        $this->assertSame(2, DB::table('metas')->where('key', 'color')->count());
        $this->assertSame(['Branch-A', 'branch-a'], DB::table('metas')->where('key', 'color')->orderBy('branch_key')->pluck('branch_key')->all());

        StaticBranchResolver::$key = 'Branch-A';
        $this->assertSame('Red', $product->getMeta('color'));
        $this->assertSame(['color' => 'Red', 'price' => '10.0000000000'], $product->getMetas());
        $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('color', '=', 'Red')->get()));
        $this->assertSame(2, Meta::purge($product));

        StaticBranchResolver::$key = 'branch-a';
        $this->assertSame('red', $product->getMeta('color'));
        $this->assertSame(2, DB::table('metas')->count());
    }

    public function test_ambiguous_branch_keys_are_rejected(): void
    {
        config([
            'meta.branch.enabled' => true,
            'meta.branch.resolver' => StaticBranchResolver::class,
        ]);
        $product = $this->product();
        StaticBranchResolver::$key = 'branch-a ';

        $this->expectException(MetaException::class);
        $product->setMeta('color', 'red');
    }
}
