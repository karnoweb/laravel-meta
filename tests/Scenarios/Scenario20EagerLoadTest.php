<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Karnoweb\Meta\Tests\Fixtures\Product;

class Scenario20EagerLoadTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            $product = $this->product('P'.$i);
            $product->setMeta('color', 'c'.$i);
            $product->setMeta('price', ($i + 8).'.00', versioned: true, type: 'decimal');
            $product->setMeta('price', ($i + 9).'.00', type: 'decimal');
        }

        $loaded = null;
        $queries = $this->queries(function () use (&$loaded): void {
            $loaded = Product::query()->withMeta(['color', 'price'])->orderBy('id')->get();
        });

        $this->assertLessThanOrEqual(3, $queries);
        $this->assertCount(100, $loaded);
        $this->assertSame(0, $this->queries(function () use ($loaded): void {
            foreach ($loaded as $product) {
                $product->getMeta('color');
                $product->getMeta('price');
            }
        }));
        $this->assertSame('c1', $loaded->first()->getMeta('color'));
        $this->assertSame('109.0000000000', $loaded->last()->getMeta('price'));
    }
}
