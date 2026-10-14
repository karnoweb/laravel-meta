<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\TestCase;

abstract class ScenarioTestCase extends TestCase
{
    protected function freeze(string $instant): void
    {
        Carbon::setTestNow(CarbonImmutable::parse($instant, 'UTC'));
    }

    protected function metaCount(): int
    {
        return (int) DB::table('metas')->count();
    }

    protected function versionCount(?Product $product = null, ?string $key = null): int
    {
        $query = DB::table('meta_versions');

        if ($product !== null && $key !== null) {
            $metaId = DB::table('metas')
                ->where('metable_id', (string) $product->getKey())
                ->where('key', $key)
                ->value('id');
            $query->where('meta_id', $metaId);
        }

        return (int) $query->count();
    }
}
