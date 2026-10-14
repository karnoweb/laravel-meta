<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

class Scenario02VersionOptInTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $product = $this->product('A');
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');
        $this->assertSame('100.0000000000', $product->getMeta('price'));
        $this->assertSame(1, $this->versionCount($product, 'price'));

        $product->setMeta('price', '110.00', type: 'decimal');

        $this->assertSame('110.0000000000', $product->getMeta('price'));
        $this->assertSame(1, $this->metaCount());
        $this->assertSame(2, $this->versionCount($product, 'price'));
        $this->assertSame(['100.0000000000', '110.0000000000'], $product->metaHistory('price')->map->value()->all());
    }
}
