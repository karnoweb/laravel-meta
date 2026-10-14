<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\Fixtures\StaticTenantResolver;

class Scenario22TenantIsolationTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        config([
            'meta.tenant.enabled' => true,
            'meta.tenant.resolver' => StaticTenantResolver::class,
        ]);
        $product = $this->product('A');

        StaticTenantResolver::$key = 'tenant-a';
        $product->setMeta('color', 'red');
        $product->setMeta('price', '10.00', versioned: true, type: 'decimal');

        StaticTenantResolver::$key = 'tenant-b';
        $product->setMeta('color', 'blue');
        $this->assertSame([], $this->ids(Product::query()->whereMeta('color', '=', 'red')->get()));
        $this->assertCount(0, $product->metaHistory('price'));
        $this->assertTrue($product->deleteMeta('color'));

        StaticTenantResolver::$key = 'tenant-a';
        $this->assertSame('red', $product->getMeta('color'));
        $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('color', '=', 'red')->get()));
        $this->assertCount(1, $product->metaHistory('price'));
        $this->assertSame('10.0000000000', $product->getMeta('price'));
    }
}
