<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Carbon\CarbonImmutable;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\TestCase;

class ReadmeGuideTest extends TestCase
{
    public function test_color_batch_read_matches_the_guide(): void
    {
        $product = $this->product();
        $product->setMeta('color', 'red');
        $product->setMeta('note', null);
        $product->setMetas(['color' => 'blue', 'visible' => true, 'count' => 10]);

        $this->assertSame('blue', $product->getMeta('color'));
        $this->assertSame('fallback', $product->getMeta('unknown', 'fallback'));
        $this->assertTrue($product->hasMeta('note'));
        $this->assertSame([
            'color' => 'blue',
            'count' => 10,
            'note' => null,
        ], $product->getMetas(['color', 'count', 'note', 'missing']));
    }

    public function test_filter_counts_match_the_guide(): void
    {
        $this->product('a')->setMetas(['count' => 5, 'color' => 'red']);
        $ten = $this->product('b');
        $ten->setMetas(['count' => 10, 'color' => 'red']);
        $this->product('c')->setMetas(['count' => 20, 'color' => 'blue']);
        $thirty = $this->product('d');
        $thirty->setMetas(['count' => 30, 'color' => 'red']);

        $this->assertSame(3, Product::query()->whereMeta('count', '>=', 10)->count());
        $this->assertSame(
            [$ten->id, $thirty->id],
            $this->ids(Product::query()->whereMeta('count', '>=', 10)->whereMeta('color', '=', 'red')->get()),
        );
    }

    public function test_versioned_price_matches_the_guide(): void
    {
        $product = $this->product();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC'));
        $product->setMeta('price', '100.00', versioned: true, type: 'decimal');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'));
        $product->setMeta('price', '120.00', type: 'decimal');
        $product->setMetaAt('price', '150.00', '2027-01-01 00:00:00', type: 'decimal');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 00:00:00', 'UTC'));

        $this->assertSame('120.0000000000', $product->getMeta('price'));
        $this->assertSame('100.0000000000', $product->getMetaAt('price', '2026-03-01 00:00:00'));
        $this->assertSame([], $this->ids(Product::query()->whereMeta('price', '=', '100.00', type: 'decimal')->get()));
        $this->assertSame(
            [$product->id],
            $this->ids(Product::query()->whereMetaAt('price', '=', '100.00', '2026-03-01 00:00:00', type: 'decimal')->get()),
        );

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-02 00:00:00', 'UTC'));
        $product->refreshMeta();
        $this->assertSame('150.0000000000', $product->getMeta('price'));
    }
}
