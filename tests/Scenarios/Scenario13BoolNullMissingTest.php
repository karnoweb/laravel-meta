<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Karnoweb\Meta\Tests\Fixtures\Product;

class Scenario13BoolNullMissingTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $false = $this->product('false');
        $zero = $this->product('zero');
        $null = $this->product('null');
        $missing = $this->product('missing');
        $false->setMeta('flag', false);
        $zero->setMeta('flag', 0);
        $null->setMeta('flag', null);

        $this->assertSame([$false->id], $this->ids(Product::query()->whereMeta('flag', '=', false)->get()));
        $this->assertSame([$zero->id], $this->ids(Product::query()->whereMeta('flag', '=', 0)->get()));
        $this->assertSame([$null->id], $this->ids(Product::query()->whereMetaNull('flag')->get()));
        $this->assertSame([$missing->id], $this->ids(Product::query()->whereDoesntHaveMeta('flag')->get()));
        $this->assertNotContains($missing->id, $this->ids(Product::query()->whereMetaNull('flag')->get()));
        $this->assertNotContains($null->id, $this->ids(Product::query()->whereDoesntHaveMeta('flag')->get()));
    }
}
