<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Karnoweb\Meta\Tests\Fixtures\Product;

class Scenario18MixedInTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $bool = $this->product('bool');
        $int = $this->product('int');
        $string = $this->product('string');
        $null = $this->product('null');
        $bool->setMeta('flag', false);
        $int->setMeta('flag', 0);
        $string->setMeta('flag', '0');
        $null->setMeta('flag', null);

        $found = Product::query()->whereMetaIn('flag', [false, 0, '0', null])->get();

        $this->assertSame([$bool->id, $int->id, $string->id, $null->id], $this->ids($found));
        $this->assertSame([], $this->ids(Product::query()->whereMetaIn('flag', [])->get()));
        $this->assertSame([$bool->id], $this->ids(Product::query()->whereMetaIn('flag', [false])->get()));
    }
}
