<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Illuminate\Support\Facades\DB;

class Scenario01BasicMetaTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $product = $this->product('A');
        $product->setMeta('color', 'red');
        $this->assertSame('red', $product->getMeta('color'));
        $this->assertSame(1, $this->metaCount());
        $this->assertSame(0, $this->versionCount());

        $product->setMeta('color', 'blue');
        $product->setMeta('color', 'green');

        $this->assertSame('green', $product->getMeta('color'));
        $this->assertSame(1, DB::table('metas')->where('key', 'color')->count());
        $this->assertSame(0, $this->versionCount());
        $this->assertSame('green', DB::table('metas')->value('value_string'));
    }
}
