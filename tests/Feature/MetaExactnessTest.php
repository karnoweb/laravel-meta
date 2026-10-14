<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Karnoweb\Meta\Exceptions\MetaException;
use Karnoweb\Meta\Facades\Meta;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\TestCase;
use RuntimeException;
use Throwable;

class MetaExactnessTest extends TestCase
{
    public function test_decimal_round_trip_and_range_stay_exact(): void
    {
        $values = [
            '0.1' => '0.1000000000',
            '0.10' => '0.1000000000',
            '0.1000000001' => '0.1000000001',
            '10.20' => '10.2000000000',
            '-2.50' => '-2.5000000000',
            '-10.00' => '-10.0000000000',
            '-0' => '0.0000000000',
            '10000000000000000000.0000000001' => '10000000000000000000.0000000001',
        ];
        $products = [];

        $pointOne = [];

        foreach ($values as $input => $canonical) {
            $product = $this->product((string) $input);
            $product->setMeta('amount', (string) $input, type: 'decimal');
            $products[$canonical] = $product;

            if ($canonical === '0.1000000000') {
                $pointOne[] = $product->id;
            }

            $this->assertSame($canonical, $product->getMeta('amount'));
        }

        sort($pointOne);
        $this->assertSame(
            $pointOne,
            $this->ids(Product::query()->whereMeta('amount', '=', '0.10', type: 'decimal')->get()),
        );
        $this->assertSame(
            [$products['0.1000000001']->id],
            $this->ids(Product::query()->whereMeta('amount', '=', '0.1000000001', type: 'decimal')->get()),
        );
        $this->assertSame(
            [$products['-2.5000000000']->id, $products['-10.0000000000']->id],
            $this->ids(Product::query()->whereMetaBetween('amount', '-10', '-2.50', type: 'decimal')->orderBy('id')->get()),
        );
        $this->assertSame(
            [],
            $this->ids(Product::query()->whereMeta('amount', '=', '0.1000000000', type: 'decimal')->whereKey($products['0.1000000001']->id)->get()),
        );
    }

    public function test_get_metas_omits_missing_and_keeps_explicit_null(): void
    {
        $product = $this->product();
        $product->setMeta('color', 'red');
        $product->setMeta('visible', false);
        $product->setMeta('note', null);
        $product->setMeta('price', '150.00', versioned: true, type: 'decimal');
        $product->setMetaAt('price', '180.00', '2027-01-01 00:00:00', type: 'decimal');

        $this->assertSame(0, $this->queries(fn () => $product->getMetas([])));
        $this->assertSame([], $product->getMetas([]));

        $queries = $this->queries(fn () => $loaded = $product->getMetas(['color', 'visible', 'note', 'missing']));
        $this->assertLessThanOrEqual(2, $queries);
        $this->assertSame([
            'color' => 'red',
            'visible' => false,
            'note' => null,
        ], $product->getMetas(['color', 'visible', 'note', 'missing']));
        $this->assertSame(0, $this->queries(fn () => $product->getMetas(['color', 'visible', 'note', 'missing'])));
        $this->assertSame('150.0000000000', $product->getMetas()['price']);
        $this->assertSame($product->getMetas(['color', 'note']), Meta::getMany($product, ['color', 'note']));

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-02 00:00:00', 'UTC'));
        $product->refreshMeta();
        $this->assertSame('180.0000000000', $product->getMetas(['price'])['price']);
    }

    public function test_history_page_is_deterministic(): void
    {
        $product = $this->product();
        $product->setMeta('price', '1.00', versioned: true, type: 'decimal');

        for ($i = 2; $i <= 120; $i++) {
            $product->setMetaAt('price', $i.'.00', sprintf('2026-01-01 00:00:%02d', $i % 60), type: 'decimal');
        }

        $page = $product->metaHistoryPage('price', 50);
        $this->assertSame(120, $page->total());
        $this->assertCount(50, $page->items());
        $ids = [];

        foreach ([1, 2, 3] as $number) {
            Paginator::currentPageResolver(static fn (): int => $number);

            foreach ($product->metaHistoryPage('price', 50)->items() as $version) {
                $ids[] = $version->id;
            }
        }

        Paginator::currentPageResolver(static fn (): int => (int) request()->input('page', 1));

        $this->assertCount(120, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
        $this->assertSame($product->metaHistory('price')->pluck('id')->all(), $ids);
    }

    public function test_purge_failure_restores_roots_and_versions(): void
    {
        $product = $this->product();
        $product->setMeta('color', 'red');
        $product->setMeta('price', '10.00', versioned: true, type: 'decimal');
        DB::unprepared("create trigger meta_purge_fail before delete on metas begin select raise(abort, 'purge-fail'); end");

        try {
            Meta::purge($product);
            $this->fail('The purge trigger should abort the delete.');
        } catch (Throwable) {
            $this->assertSame(2, DB::table('metas')->count());
            $this->assertSame(1, DB::table('meta_versions')->count());
            $this->assertSame('red', $product->getMeta('color'));
        } finally {
            DB::unprepared('drop trigger if exists meta_purge_fail');
        }
    }

    public function test_rolled_back_transaction_does_not_keep_a_stale_snapshot(): void
    {
        $product = $this->product();
        $product->setMeta('color', 'red');

        try {
            DB::transaction(function () use ($product): void {
                $product->setMeta('color', 'blue');
                Meta::load([$product], ['color']);
                throw new RuntimeException('stop');
            });
        } catch (RuntimeException) {
            $this->assertSame('red', $product->getMeta('color'));
        }
    }

    public function test_config_above_the_column_width_is_rejected(): void
    {
        $product = $this->product();
        config(['meta.max_string_length' => 800]);

        $this->expectException(MetaException::class);
        $product->setMeta('label', 'red');
    }

    public function test_long_unicode_string_is_stored_and_found(): void
    {
        $product = $this->product();
        $value = str_repeat('س', 400).str_repeat('😀', 112);
        $this->assertSame(512, mb_strlen($value));
        $product->setMeta('label', $value);

        $this->assertSame($value, $product->getMeta('label'));
        $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('label', '=', $value)->get()));
    }

    public function test_chunked_load_and_purge_do_not_follow_model_count(): void
    {
        $products = [];

        for ($i = 1; $i <= 1000; $i++) {
            $product = $this->product('P'.$i);
            $product->setMeta('color', 'c'.$i);
            $products[] = $product;
        }

        $queries = $this->queries(function () use ($products): void {
            Meta::load($products, ['color']);

            foreach ($products as $index => $product) {
                $this->assertSame('c'.($index + 1), $product->getMeta('color'));
            }
        });

        $this->assertLessThanOrEqual(4, $queries);
        $this->assertSame(1000, Meta::purgeMany($products));
        $this->assertSame(0, DB::table('metas')->count());
    }
}
