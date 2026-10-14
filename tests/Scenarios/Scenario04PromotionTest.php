<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

class Scenario04PromotionTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $this->freeze('2027-04-01 00:00:00');
        $product = $this->product('A');
        $product->setMeta('status', 'draft');
        $this->assertSame(0, $this->versionCount());

        $product->setMeta('status', 'active', versioned: true);
        $history = $product->metaHistory('status');

        $this->assertSame(['draft', 'active'], $history->map->value()->all());
        $this->assertSame('active', $product->getMeta('status'));
        $this->assertSame(1, $this->metaCount());
        $this->assertSame(2, $this->versionCount($product, 'status'));
        $this->assertNull(\Illuminate\Support\Facades\DB::table('metas')->value('value_string'));
    }
}
