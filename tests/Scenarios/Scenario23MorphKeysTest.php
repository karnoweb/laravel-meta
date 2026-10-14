<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Scenarios;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Karnoweb\Meta\Tests\Fixtures\Product;
use Karnoweb\Meta\Tests\Fixtures\UlidRecord;
use Karnoweb\Meta\Tests\Fixtures\User;
use Karnoweb\Meta\Tests\Fixtures\UuidRecord;

class Scenario23MorphKeysTest extends ScenarioTestCase
{
    public function test_complete_scenario(): void
    {
        Relation::enforceMorphMap([
            'product' => Product::class,
            'user' => User::class,
            'uuid' => UuidRecord::class,
            'ulid' => UlidRecord::class,
        ]);

        try {
            $product = $this->product('Numeric');
            $user = User::query()->create(['name' => 'User']);
            $uuid = new UuidRecord(['name' => 'UUID', 'id' => (string) Str::uuid()]);
            $uuid->save();
            $ulid = new UlidRecord(['name' => 'ULID', 'id' => (string) Str::ulid()]);
            $ulid->save();

            $product->setMeta('code', 'p');
            $user->setMeta('code', 'u');
            $uuid->setMeta('code', 'uuid');
            $ulid->setMeta('code', 'ulid');

            $this->assertSame('p', $product->getMeta('code'));
            $this->assertSame('u', $user->getMeta('code'));
            $this->assertSame('uuid', $uuid->getMeta('code'));
            $this->assertSame('ulid', $ulid->getMeta('code'));
            $this->assertSame([$product->id], $this->ids(Product::query()->whereMeta('code', '=', 'p')->get()));
            $this->assertSame([], $this->ids(Product::query()->whereMeta('code', '=', 'u')->get()));
        } finally {
            Relation::requireMorphMap(false);
            Relation::morphMap([], false);
        }
    }
}
