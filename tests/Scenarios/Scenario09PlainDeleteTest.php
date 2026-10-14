<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

class Scenario09PlainDeleteTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $product = $this->product('A');
        $product->setMeta('note', null);
        $product->setMeta('visible', false);
        $product->setMeta('color', 'red');

        $this->assertTrue($product->hasMeta('note'));
        $this->assertTrue($product->hasMeta('visible'));
        $this->assertNull($product->getMeta('note'));
        $this->assertFalse($product->getMeta('visible'));
        $this->assertFalse($product->hasMeta('missing'));

        $this->assertTrue($product->deleteMeta('color'));
        $this->assertSame(0, $this->versionCount());
        $this->assertFalse($product->hasMeta('color'));
        $this->assertFalse($product->deleteMeta('color'));
        $this->assertTrue($product->hasMeta('note'));
        $this->assertSame(2, $this->metaCount());
    }
}
