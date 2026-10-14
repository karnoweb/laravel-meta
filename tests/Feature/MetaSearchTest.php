<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Karnoweb\Meta\Exceptions\MetaQueryException;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\TestCase;

class MetaSearchTest extends TestCase
{
    public function test_typed_equality_does_not_cross_types(): void
    {
        $string = $this->product('string');
        $integer = $this->product('integer');
        $boolean = $this->product('boolean');
        $decimal = $this->product('decimal');
        $empty = $this->product('empty');
        $null = $this->product('null');

        $string->setMeta('code', '10');
        $integer->setMeta('code', 10);
        $boolean->setMeta('code', false);
        $decimal->setMeta('code', '10.20', type: 'decimal');
        $empty->setMeta('code', '');
        $null->setMeta('code', null);

        $this->assertSame([$string->id], $this->ids(Product::query()->whereMeta('code', '=', '10')->get()));
        $this->assertSame([$integer->id], $this->ids(Product::query()->whereMeta('code', '=', 10)->get()));
        $this->assertSame([$boolean->id], $this->ids(Product::query()->whereMeta('code', '=', false)->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMeta('code', '=', 1)->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMeta('code', '=', 0)->get()));
        $this->assertSame([$decimal->id], $this->ids(Product::query()->whereMeta('code', '=', '10.2', type: 'decimal')->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMeta('code', '=', '10.00', type: 'decimal')->get()));
        $this->assertSame([$empty->id], $this->ids(Product::query()->whereMeta('code', '=', '')->get()));
        $this->assertSame([$null->id], $this->ids(Product::query()->whereMetaNull('code')->get()));
        $this->assertSame([$empty->id], $this->ids(Product::query()->whereMetaNotNull('code')->where('name', 'empty')->get()));
        $this->assertNotContains($null->id, $this->ids(Product::query()->whereMetaNotNull('code')->get()));
    }

    public function test_comparisons_ranges_and_presence(): void
    {
        $low = $this->product('low');
        $mid = $this->product('mid');
        $high = $this->product('high');
        $missing = $this->product('missing');
        $low->setMeta('count', -5);
        $mid->setMeta('count', 10);
        $high->setMeta('count', 40);

        $this->assertSame([$high->id], $this->ids(Product::query()->whereMeta('count', '>', 10)->get()));
        $this->assertSame([$mid->id, $high->id], $this->ids(Product::query()->whereMeta('count', '>=', 10)->get()));
        $this->assertSame([$low->id], $this->ids(Product::query()->whereMeta('count', '<', 0)->get()));
        $this->assertSame([$low->id, $high->id], $this->ids(Product::query()->whereMeta('count', '!=', 10)->get()));
        $this->assertSame([$mid->id], $this->ids(Product::query()->whereMetaBetween('count', 10, 20)->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMetaBetween('count', 40, 10)->get()));
        $this->assertSame([$low->id, $mid->id, $high->id], $this->ids(Product::query()->whereHasMeta('count')->orderBy('id')->get()));
        $this->assertSame([$missing->id], $this->ids(Product::query()->whereDoesntHaveMeta('count')->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMetaIn('count', [])->get()));
        $this->assertSame([$low->id, $high->id], $this->ids(Product::query()->whereMetaIn('count', [-5, 40, '40'])->get()));
    }

    public function test_string_like_date_datetime_and_json(): void
    {
        $laravel = $this->product('laravel');
        $other = $this->product('other');
        $laravel->setMeta('title', 'آموزش Laravel 😀');
        $other->setMeta('title', 'Symfony');
        $laravel->setMeta('birthday', '2026-01-05', type: 'date');
        $other->setMeta('birthday', '2026-02-01', type: 'date');
        $laravel->setMeta('start_at', CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'));
        $other->setMeta('start_at', CarbonImmutable::parse('2026-06-01 00:00:00.000001', 'UTC'));
        $laravel->setMeta('tags', ['vip', 'sale']);
        $other->setMeta('tags', ['free']);
        $laravel->setMeta('profile', ['tier' => ['name' => 'gold']]);

        $this->assertSame([$laravel->id], $this->ids(Product::query()->whereMeta('title', '=', 'آموزش Laravel 😀')->get()));
        $this->assertSame([$laravel->id], $this->ids(Product::query()->whereMetaLike('title', '%Laravel%')->get()));
        $this->assertSame([$laravel->id], $this->ids(Product::query()->whereMetaLike('title', '%آموزش%')->get()));
        $this->assertSame([$laravel->id], $this->ids(Product::query()->whereMetaLike('title', '%😀%')->get()));
        $this->assertSame([$laravel->id], $this->ids(Product::query()->whereMeta('birthday', '=', '2026-01-05', type: 'date')->get()));
        $this->assertSame([$laravel->id, $other->id], $this->ids(Product::query()->whereMeta('birthday', '<=', '2026-02-01', type: 'date')->get()));
        $this->assertSame([$other->id], $this->ids(Product::query()->whereMeta('start_at', '>', CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'))->get()));
        $this->assertSame([$laravel->id], $this->ids(Product::query()->whereMeta('start_at', '<=', CarbonImmutable::parse('2026-06-01 03:00:00+03:00'))->get()));
        $this->assertSame([$laravel->id], $this->ids(Product::query()->whereMetaJsonContains('tags', 'vip')->get()));
        $this->assertSame([], $this->ids(Product::query()->whereMetaJsonContains('tags', 'missing')->get()));

        $nested = $this->ids(Product::query()->whereMetaJsonContains('profile', ['tier' => ['name' => 'gold']])->get());

        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame([], $nested);
        } else {
            $this->assertSame([$laravel->id], $nested);
        }
    }

    public function test_decimal_ranges_are_exact(): void
    {
        $a = $this->product('a');
        $b = $this->product('b');
        $c = $this->product('c');
        $d = $this->product('d');
        $a->setMeta('price', '0.10', type: 'decimal');
        $b->setMeta('price', '0.20', type: 'decimal');
        $c->setMeta('price', '10.20', type: 'decimal');
        $d->setMeta('price', '100.0000000001', type: 'decimal');

        $this->assertSame([$a->id], $this->ids(Product::query()->whereMeta('price', '=', '0.1', type: 'decimal')->get()));
        $this->assertSame([$a->id, $b->id], $this->ids(Product::query()->whereMetaBetween('price', '0.10', '0.20', type: 'decimal')->get()));
        $this->assertSame([$d->id], $this->ids(Product::query()->whereMeta('price', '>', '100', type: 'decimal')->get()));
        $this->assertSame('100.0000000001', $d->getMeta('price'));
        $this->assertSame([], $this->ids(Product::query()->whereMeta('price', '=', 10)->get()));
    }

    public function test_mixed_filters_do_not_duplicate_paginated_rows(): void
    {
        $ids = [];

        foreach (['active', 'active', 'active', 'active', 'active', 'draft'] as $index => $status) {
            $product = $this->product('p'.$index);
            $product->status = $status;
            $product->save();
            $product->setMeta('status', $status);
            $product->setMeta('count', $index);
            $ids[] = $product->id;
        }

        $page = Product::query()
            ->where('status', 'active')
            ->whereMeta('status', '=', 'active')
            ->whereMeta('count', '>=', 0)
            ->orderBy('id')
            ->paginate(2);

        $this->assertSame(5, $page->total());
        $this->assertCount(2, $page->items());
        $this->assertSame([$ids[0], $ids[1]], $this->ids($page->items()));
    }

    public function test_or_where_meta_is_grouped(): void
    {
        $red = $this->product('red');
        $blue = $this->product('blue');
        $green = $this->product('green');
        $red->setMeta('color', 'red');
        $blue->setMeta('color', 'blue');
        $green->setMeta('color', 'green');
        $red->status = 'active';
        $blue->status = 'archived';
        $green->status = 'active';
        $red->save();
        $blue->save();
        $green->save();

        $ids = $this->ids(Product::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereMeta('color', '=', 'red')->orWhereMeta('color', '=', 'blue');
            })
            ->get());

        $this->assertSame([$red->id], $ids);
    }

    public function test_invalid_search_arguments_are_rejected(): void
    {
        $this->expectException(MetaQueryException::class);
        Product::query()->whereMeta('color', 'LIKE', 'red')->get();
    }

    public function test_between_rejects_mixed_types_and_bool_inequality(): void
    {
        $product = $this->product();
        $product->setMeta('visible', false);

        try {
            Product::query()->whereMetaBetween('count', 1, '2')->get();
            $this->fail('Mixed between bounds must be rejected.');
        } catch (MetaQueryException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(MetaQueryException::class);
        Product::query()->whereMeta('visible', '>', false)->get();
    }
}
