<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Carbon\CarbonImmutable;
use Karnoweb\Meta\Tests\Fixtures\Product;

class Scenario16DateTimeTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        $day = $this->product('day');
        $early = $this->product('early');
        $later = $this->product('later');
        $day->setMeta('birthday', '2027-01-05', type: 'date');
        $early->setMeta('start_at', CarbonImmutable::parse('2027-01-05 00:00:00.000001', 'UTC'));
        $offset = CarbonImmutable::parse('2027-01-05 03:30:00+03:30');
        $later->setMeta('start_at', $offset);

        $this->assertSame([$day->id], $this->ids(Product::query()->whereMeta('birthday', '=', '2027-01-05', type: 'date')->get()));
        $this->assertSame('2027-01-05', $day->getMeta('birthday')->toDateString());
        $this->assertSame('2027-01-05 00:00:00.000000', $later->getMeta('start_at')->utc()->format('Y-m-d H:i:s.u'));
        $this->assertSame([$later->id], $this->ids(Product::query()->whereMeta('start_at', '=', $offset)->get()));
        $this->assertSame([$early->id], $this->ids(Product::query()->whereMeta('start_at', '>', CarbonImmutable::parse('2027-01-05 00:00:00.000000', 'UTC'))->whereMeta('start_at', '<', CarbonImmutable::parse('2027-01-05 00:00:00.000002', 'UTC'))->get()));
    }
}
