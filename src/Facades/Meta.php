<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Karnoweb\Meta\MetaManager;

/**
 * @method static Model set(Model $owner, string $key, mixed $value, ?bool $versioned = null, ?string $type = null)
 * @method static Model setAt(Model $owner, string $key, mixed $value, \DateTimeInterface|string $at, ?string $type = null)
 * @method static Model setMany(Model $owner, array $values)
 * @method static mixed get(Model $owner, string $key, mixed $default = null)
 * @method static array getMany(Model $owner, ?array $keys = null)
 * @method static mixed getAt(Model $owner, string $key, \DateTimeInterface|string $at, mixed $default = null)
 * @method static bool has(Model $owner, string $key)
 * @method static bool delete(Model $owner, string $key)
 * @method static Collection history(Model $owner, string $key)
 * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator historyPage(Model $owner, string $key, int $perPage = 50)
 * @method static void load(iterable|Model $models, ?array $keys = null)
 * @method static Model refresh(Model $owner)
 * @method static int purge(Model $owner)
 * @method static int purgeMany(iterable $models)
 *
 * @see MetaManager
 */
class Meta extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MetaManager::class;
    }
}
