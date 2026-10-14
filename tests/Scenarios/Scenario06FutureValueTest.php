<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

class Scenario06FutureValueTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $this->freeze('2027-01-01 00:00:00');
        $product = $this->product('A');
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');
        $product->setMetaAt('price', '200.00', '2027-02-01 00:00:00', type: 'decimal');

        $this->freeze('2027-01-31 23:59:59');
        $this->assertSame('100.0000000000', $product->getMeta('price'));

        $this->freeze('2027-02-01 00:00:00');
        $this->assertSame('200.0000000000', $product->getMeta('price'));
        $this->assertSame(2, $this->versionCount($product, 'price'));
    }
}
