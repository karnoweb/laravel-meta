<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

class Scenario08SameInstantTieTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $product = $this->product('A');
        $at = '2026-03-01 00:00:00';
        $product->setMetaAt('price', '100.00', $at, type: 'decimal');
        $product->setMetaAt('price', '180.00', $at, type: 'decimal');
        $history = $product->metaHistory('price');

        $this->assertTrue($history[1]->id > $history[0]->id);
        $this->assertSame($history[0]->effective_at, $history[1]->effective_at);
        $this->assertSame('180.0000000000', $product->getMeta('price'));
        $this->assertSame([$product->id], $this->ids(\Karnoweb\Meta\Tests\Fixtures\Product::query()->whereMeta('price', '=', '180.00', type: 'decimal')->get()));
        $this->assertSame([], $this->ids(\Karnoweb\Meta\Tests\Fixtures\Product::query()->whereMeta('price', '=', '100.00', type: 'decimal')->get()));
    }
}
