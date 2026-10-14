<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

class Scenario11RestoreAfterDeleteTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $this->freeze('2027-01-01 00:00:00');
        $product = $this->product('A');
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');
        $product->deleteMeta('price');
        $product->setMetaAt('price', '120.00', '2027-04-01 00:00:00', type: 'decimal');

        $this->freeze('2027-03-01 00:00:00');
        $this->assertFalse($product->hasMeta('price'));
        $this->assertSame('missing', $product->getMeta('price', 'missing'));

        $this->freeze('2027-04-01 00:00:00');
        $this->assertTrue($product->hasMeta('price'));
        $this->assertSame('120.0000000000', $product->getMeta('price'));
        $this->assertCount(3, $product->metaHistory('price'));
    }
}
