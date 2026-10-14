<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Karnoweb\Meta\Tests\Fixtures\Product;

class Scenario19CurrentVersionFilterTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $this->freeze('2027-01-01 00:00:00');
        $product = $this->product('A');
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');
        $this->freeze('2027-02-01 00:00:00');
        $product->setMeta('price', '200.00', type: 'decimal');

        $this->assertSame([], $this->ids(Product::query()->whereMeta('price', '=', '100.00', type: 'decimal')->get()));
        $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('price', '=', '200.00', type: 'decimal')->get()));
        $this->assertSame([$product->id], $this->ids(Product::query()->whereMetaAt('price', '=', '100.00', '2027-01-15 00:00:00', type: 'decimal')->get()));
    }
}
