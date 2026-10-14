<?php

declare(strict_types=1);

namespace Karnoweb\Meta;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;
use Karnoweb\Meta\Services\MetaService;

class MetaManager
{
    public function __construct(private readonly MetaService $metas) {}

    public function set(Model $owner, string $key, mixed $value, ?bool $versioned = null, ?string $type = null): Model
    {
        return $this->metas->set($owner, $key, $value, $versioned, $type);
    }

    public function setAt(Model $owner, string $key, mixed $value, DateTimeInterface|string $at, ?string $type = null): Model
    {
        return $this->metas->setAt($owner, $key, $value, $at, $type);
    }

    /**
     * @param  array<mixed, mixed>  $values
     */
    public function setMany(Model $owner, array $values): Model
    {
        return $this->metas->setMany($owner, $values);
    }

    public function get(Model $owner, string $key, mixed $default = null): mixed
    {
        return $this->metas->get($owner, $key, $default);
    }

    /**
     * @param  list<string>|null  $keys
     * @return array<string, mixed>
     */
    public function getMany(Model $owner, ?array $keys = null): array
    {
        return $this->metas->getMany($owner, $keys);
    }

    public function getAt(Model $owner, string $key, DateTimeInterface|string $at, mixed $default = null): mixed
    {
        return $this->metas->getAt($owner, $key, $at, $default);
    }

    public function has(Model $owner, string $key): bool
    {
        return $this->metas->has($owner, $key);
    }

    public function delete(Model $owner, string $key): bool
    {
        return $this->metas->delete($owner, $key);
    }

    public function history(Model $owner, string $key): Collection
    {
        return $this->metas->history($owner, $key);
    }

    public function historyPage(Model $owner, string $key, int $perPage = 50): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->metas->historyPage($owner, $key, $perPage);
    }

    /**
     * @param  iterable<Model>|Model  $models
     * @param  list<string>|null  $keys
     */
    public function load(iterable|Model $models, ?array $keys = null): void
    {
        if ($models instanceof Model) {
            $models = [$models];
        }

        $this->metas->load($models, $keys);
    }

    public function refresh(Model $owner): Model
    {
        $this->metas->refresh($owner);

        return $owner;
    }

    public function purge(Model $owner): int
    {
        return $this->metas->purge($owner);
    }

    /**
     * @param  iterable<Model>  $models
     */
    public function purgeMany(iterable $models): int
    {
        return $this->metas->purgeMany($models);
    }

    public function service(): MetaService
    {
        return $this->metas;
    }

    /**
     * @param  iterable<Model>|BaseCollection<int, Model>  $models
     * @param  list<string>|null  $keys
     */
    public function loadCollection(iterable $models, ?array $keys = null): void
    {
        $this->load($models, $keys);
    }
}
