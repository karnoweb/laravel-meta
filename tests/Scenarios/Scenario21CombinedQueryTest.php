<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Karnoweb\Meta\Tests\Fixtures\Product;

class Scenario21CombinedQueryTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        foreach (range(1, 5) as $i) {
            $product = $this->product('P'.$i);
            $product->status = 'active';
            $product->save();
            $product->setMeta('status', 'active');
            $product->setMeta('count', $i);
        }

        $draft = $this->product('Draft');
        $draft->status = 'draft';
        $draft->save();
        $draft->setMeta('status', 'draft');

        $page = Product::query()
            ->where('status', 'active')
            ->whereMeta('status', '=', 'active')
            ->whereMeta('count', '>=', 1)
            ->orderBy('id')
            ->paginate(2);

        $this->assertSame(5, $page->total());
        $this->assertCount(2, $page->items());
        $this->assertSame(2, count(array_unique($this->ids($page->items()))));
    }
}
