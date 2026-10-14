<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\Fixtures\StaticBranchResolver;

class Scenario22BranchIsolationTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        config([
            'meta.branch.enabled' => true,
            'meta.branch.resolver' => StaticBranchResolver::class,
        ]);
        $product = $this->product('A');

        StaticBranchResolver::$key = 'branch-a';
        $product->setMeta('color', 'red');
        $product->setMeta('price', '10.00', versioned: true, type: 'decimal');

        StaticBranchResolver::$key = 'branch-b';
        $product->setMeta('color', 'blue');
        $this->assertSame([], $this->ids(Product::query()->whereMeta('color', '=', 'red')->get()));
        $this->assertCount(0, $product->metaHistory('price'));
        $this->assertTrue($product->deleteMeta('color'));

        StaticBranchResolver::$key = 'branch-a';
        $this->assertSame('red', $product->getMeta('color'));
        $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('color', '=', 'red')->get()));
        $this->assertCount(1, $product->metaHistory('price'));
        $this->assertSame('10.0000000000', $product->getMeta('price'));
    }
}
