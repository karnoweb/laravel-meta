<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

class Scenario03MixedModesTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $product = $this->product('A');
        $product->setMeta('color', 'red');
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');

        foreach (['blue', 'green', 'black'] as $color) {
            $product->setMeta('color', $color);
        }

        foreach (['110.00', '120.00', '130.00'] as $price) {
            $product->setMeta('price', $price, type: 'decimal');
        }

        $this->assertSame('black', $product->getMeta('color'));
        $this->assertSame('130.0000000000', $product->getMeta('price'));
        $this->assertSame(0, $this->versionCount($product, 'color'));
        $this->assertSame(4, $this->versionCount($product, 'price'));
        $this->assertSame(2, $this->metaCount());
    }
}
