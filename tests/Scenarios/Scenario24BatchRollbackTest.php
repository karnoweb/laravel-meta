<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Karnoweb\Meta\Exceptions\MetaTypeException;

class Scenario24BatchRollbackTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $product = $this->product('A');
        $product->setMeta('existing', 'stay');

        try {
            $product->setMetas([
                'color' => 'red',
                'size' => 'm',
                'note' => str_repeat('x', 513),
            ]);
            $this->fail('The batch must roll back.');
        } catch (MetaTypeException) {
            $this->assertSame('stay', $product->getMeta('existing'));
            $this->assertFalse($product->hasMeta('color'));
            $this->assertFalse($product->hasMeta('size'));
            $this->assertFalse($product->hasMeta('note'));
            $this->assertSame(1, $this->metaCount());
            $this->assertSame(0, $this->versionCount());
        }
    }
}
