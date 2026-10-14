<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Karnoweb\Meta\Tests\Fixtures\Product;

class Scenario15IntegerRangeTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $values = [-5, 0, 10, 20, 100];
        $products = [];

        foreach ($values as $value) {
            $product = $this->product('n'.$value);
            $product->setMeta('count', $value);
            $products[$value] = $product;
        }

        $found = Product::query()->whereMetaBetween('count', 10, 20)->orderBy('id')->limit(10)->get();

        $this->assertSame([$products[10]->id, $products[20]->id], $this->ids($found));
        $this->assertCount(2, $found);
    }
}
