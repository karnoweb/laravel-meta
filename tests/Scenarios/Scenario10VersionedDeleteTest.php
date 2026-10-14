<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

class Scenario10VersionedDeleteTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $product = $this->product('A');
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');
        $this->assertTrue($product->deleteMeta('price'));

        $this->assertSame('missing', $product->getMeta('price', 'missing'));
        $this->assertFalse($product->hasMeta('price'));
        $this->assertCount(2, $product->metaHistory('price'));
        $this->assertSame('100.0000000000', $product->metaHistory('price')->first()->value());
        $this->assertTrue($product->metaHistory('price')->last()->is_deleted);
        $this->assertSame(1, $this->metaCount());
    }
}
