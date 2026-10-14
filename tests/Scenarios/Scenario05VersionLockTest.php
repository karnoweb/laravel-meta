<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Karnoweb\Meta\Exceptions\MetaVersionException;

class Scenario05VersionLockTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $product = $this->product('A');
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');
        $product->setMeta('price', '110.00', type: 'decimal');
        $before = $this->versionCount($product, 'price');

        try {
            $product->setMeta('price', '90.00', versioned: false, type: 'decimal');
            $this->fail('Explicit versioned:false must throw.');
        } catch (MetaVersionException $exception) {
            $this->assertStringContainsString('cannot be switched off', $exception->getMessage());
        }

        $this->assertSame($before, $this->versionCount($product, 'price'));
        $this->assertSame('110.0000000000', $product->getMeta('price'));
        $this->assertSame(1, $this->metaCount());
    }
}
