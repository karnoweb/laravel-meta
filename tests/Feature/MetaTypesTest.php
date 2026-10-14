<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Karnoweb\Meta\Exceptions\MetaException;
use Karnoweb\Meta\Exceptions\MetaTypeException;
use Karnoweb\Meta\Tests\TestCase;
use stdClass;

class MetaTypesTest extends TestCase
{
    public function test_all_supported_value_types_round_trip_correctly(): void
    {
        $product = $this->product();
        $at = CarbonImmutable::parse('2026-05-01 15:04:05.123456', 'UTC');

        $product->setMeta('empty', null);
        $product->setMeta('label', 'سلام 😀');
        $product->setMeta('count', 42);
        $product->setMeta('zero', 0);
        $product->setMeta('negative', -7);
        $product->setMeta('ratio', 1.5);
        $product->setMeta('price', '120.5000000000', type: 'decimal');
        $product->setMeta('visible', false);
        $product->setMeta('featured', true);
        $product->setMeta('birthday', '2026-01-05', type: 'date');
        $product->setMeta('start_at', $at);
        $product->setMeta('tags', ['vip', 'nested' => ['tier' => 'gold']]);

        $this->assertNull($product->getMeta('empty'));
        $this->assertSame('سلام 😀', $product->getMeta('label'));
        $this->assertSame(42, $product->getMeta('count'));
        $this->assertSame(0, $product->getMeta('zero'));
        $this->assertSame(-7, $product->getMeta('negative'));
        $this->assertSame('1.5000000000', $product->getMeta('ratio'));
        $this->assertSame('120.5000000000', $product->getMeta('price'));
        $this->assertFalse($product->getMeta('visible'));
        $this->assertTrue($product->getMeta('featured'));
        $this->assertSame('2026-01-05', $product->getMeta('birthday')->utc()->toDateString());
        $this->assertSame('2026-05-01 15:04:05.123456', $product->getMeta('start_at')->utc()->format('Y-m-d H:i:s.u'));
        $this->assertSame(['vip', 'nested' => ['tier' => 'gold']], $product->getMeta('tags'));
        $this->assertSame(0, DB::table('meta_versions')->count());
    }

    public function test_type_change_clears_old_value_columns(): void
    {
        $product = $this->product();
        $product->setMeta('code', '100');
        $product->setMeta('code', 100);

        $row = DB::table('metas')->where('key', 'code')->first();

        $this->assertSame('int', $row->value_type);
        $this->assertNull($row->value_string);
        $this->assertSame(100, (int) $row->value_integer);
        $this->assertSame(100, $product->getMeta('code'));
    }

    public function test_numeric_string_does_not_auto_cast(): void
    {
        $product = $this->product();
        $product->setMeta('stock_label', '10');
        $product->setMeta('flag', 'false');

        $this->assertSame('10', $product->getMeta('stock_label'));
        $this->assertSame('false', $product->getMeta('flag'));
        $this->assertSame('string', DB::table('metas')->where('key', 'stock_label')->value('value_type'));
        $this->assertNull(DB::table('metas')->where('key', 'stock_label')->value('value_integer'));
        $this->assertNull(DB::table('metas')->where('key', 'flag')->value('value_boolean'));
    }

    public function test_invalid_decimal_date_json_key_and_overflow_are_rejected(): void
    {
        $product = $this->product();

        $this->assertRejected(fn () => $product->setMeta('price', '10.12345678901', type: 'decimal'));
        $this->assertRejected(fn () => $product->setMeta('price', str_repeat('9', 21), type: 'decimal'));
        $this->assertRejected(fn () => $product->setMeta('birthday', '2027-02-31', type: 'date'));
        $this->assertRejected(fn () => $product->setMeta('birthday', '01/05/2027', type: 'date'));
        $this->assertRejected(fn () => $product->setMeta('blob', new stdClass));
        $this->assertRejected(fn () => $product->setMeta('note', str_repeat('a', 513)));
        $this->assertRejected(fn () => $product->setMeta('', 'x'));
        $this->assertRejected(fn () => $product->setMeta(str_repeat('k', 121), 'x'));
        $this->assertRejected(fn () => $product->setMeta("bad\nkey", 'x'));
        $this->assertRejected(fn () => $product->setMeta('count', '10', type: 'int'));
        $this->assertRejected(fn () => $product->setMeta('visible', 1, type: 'bool'));
        $this->assertRejected(fn () => $product->setMeta('when', '2027-01-01 00:00:00', type: 'date'));

        config(['meta.max_json_bytes' => 32]);
        $this->assertRejected(fn () => $product->setMeta('payload', ['value' => str_repeat('x', 40)]));

        $this->assertSame(0, DB::table('metas')->count());
        $this->assertSame(PHP_INT_MAX, $product->setMeta('edge', PHP_INT_MAX)->getMeta('edge'));
        $this->assertSame(PHP_INT_MIN, $product->setMeta('floor', PHP_INT_MIN)->getMeta('floor'));
    }

    private function assertRejected(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Invalid meta must be rejected.');
        } catch (MetaTypeException|MetaException) {
            $this->addToAssertionCount(1);
        }
    }
}
