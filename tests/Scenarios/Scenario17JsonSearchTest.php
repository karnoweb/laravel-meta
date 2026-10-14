<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Illuminate\Support\Facades\DB;
use Karnoweb\Meta\Tests\Fixtures\Product;

class Scenario17JsonSearchTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $vip = $this->product('vip');
        $free = $this->product('free');
        $vip->setMeta('tags', ['vip', 'sale']);
        $free->setMeta('tags', ['free']);
        $vip->setMeta('profile', ['tier' => ['name' => 'gold']]);

        $this->assertSame([$vip->id], $this->ids(Product::query()->whereMetaJsonContains('tags', 'vip')->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMetaJsonContains('tags', 'vip')->where('name', 'free')->get()));
        $this->assertSame(['vip', 'sale'], $vip->getMeta('tags'));

        if (DB::getDriverName() === 'sqlite') {
            $this->expectException(\Karnoweb\Meta\Exceptions\MetaQueryException::class);
            Product::query()->whereMetaJsonContains('profile', ['tier' => ['name' => 'gold']])->get();
        } else {
            $this->assertSame([$vip->id], $this->ids(Product::query()->whereMetaJsonContains('profile', ['tier' => ['name' => 'gold']])->get()));
        }
    }
}
