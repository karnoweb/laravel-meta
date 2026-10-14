<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Karnoweb\Meta\Tests\Fixtures\Product;

class Scenario14ExactDecimalTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $a = $this->product('a');
        $b = $this->product('b');
        $c = $this->product('c');
        $d = $this->product('d');
        $a->setMeta('amount', '0.10', type: 'decimal');
        $b->setMeta('amount', '0.20', type: 'decimal');
        $c->setMeta('amount', '10.20', type: 'decimal');
        $d->setMeta('amount', '100.0000000001', type: 'decimal');

        $this->assertSame('0.1000000000', $a->getMeta('amount'));
        $this->assertSame('100.0000000001', $d->getMeta('amount'));
        $this->assertSame([$a->id], $this->ids(Product::query()->whereMeta('amount', '=', '0.1000000000', type: 'decimal')->get()));
        $this->assertSame([$a->id, $b->id], $this->ids(Product::query()->whereMetaBetween('amount', '0.10', '0.20', type: 'decimal')->get()));
        $this->assertSame([$d->id], $this->ids(Product::query()->whereMeta('amount', '>', '100', type: 'decimal')->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMeta('amount', '=', '100.0000000000', type: 'decimal')->get()));
    }
}
