<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

class Scenario07BackdatedEditTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $this->freeze('2027-06-01 00:00:00');
        $product = $this->product('A');
        $product->setMetaAt('price', '100.00', '2027-01-01 00:00:00', type: 'decimal');
        $product->setMetaAt('price', '120.00', '2027-03-01 00:00:00', type: 'decimal');
        $product->setMetaAt('price', '150.00', '2027-05-01 00:00:00', type: 'decimal');
        $product->setMetaAt('price', '110.00', '2027-02-01 00:00:00', type: 'decimal');

        $this->assertSame('100.0000000000', $product->getMetaAt('price', '2027-01-15 00:00:00'));
        $this->assertSame('110.0000000000', $product->getMetaAt('price', '2027-02-15 00:00:00'));
        $this->assertSame('120.0000000000', $product->getMetaAt('price', '2027-03-15 00:00:00'));
        $this->assertSame('150.0000000000', $product->getMetaAt('price', '2027-05-15 00:00:00'));
        $this->assertSame(4, $this->versionCount($product, 'price'));
    }
}
