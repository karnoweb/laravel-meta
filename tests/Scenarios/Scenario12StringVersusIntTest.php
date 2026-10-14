<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Karnoweb\Meta\Tests\Fixtures\Product;

class Scenario12StringVersusIntTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $a = $this->product('A');
        $b = $this->product('B');
        $a->setMeta('code', '10');
        $b->setMeta('code', 10);

        $this->assertSame([$a->id], $this->ids(Product::query()->whereMeta('code', '=', '10')->get()));
        $this->assertSame([$b->id], $this->ids(Product::query()->whereMeta('code', '=', 10)->get()));
        $this->assertSame('string', \Illuminate\Support\Facades\DB::table('metas')->where('metable_id', (string) $a->id)->value('value_type'));
        $this->assertSame('int', \Illuminate\Support\Facades\DB::table('metas')->where('metable_id', (string) $b->id)->value('value_type'));
    }
}
