<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Traits;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Karnoweb\Meta\MetaManager;
use Karnoweb\Meta\Models\Meta;
use Karnoweb\Meta\Services\MetaQuery;

trait HasMeta
{
    public static function bootHasMeta(): void
    {
        static::deleted(function (Model $model): void {
            if (! static::purgesMetaOnDelete($model)) {
                return;
            }

            app(MetaManager::class)->purge($model);
        });
    }

    public function metas(): MorphMany
    {
        $related = (new Meta)->setConnection($this->getConnectionName());

        /** @var MorphMany $relation */
        $relation = $this->newMorphMany(
            $related->newQuery(),
            $this,
            $related->getTable().'.metable_type',
            $related->getTable().'.metable_id',
            $this->getKeyName(),
        );

        return $relation->where($related->getTable().'.tenant_key', app(MetaManager::class)->service()->tenantKey());
    }

    /**
     * @return list<string>|null
     */
    public function metaAllowedKeys(): ?array
    {
        if (! property_exists($this, 'metaKeys')) {
            return null;
        }

        return $this->metaKeys;
    }

    public function setMeta(string $key, mixed $value, ?bool $versioned = null, ?string $type = null): static
    {
        app(MetaManager::class)->set($this, $key, $value, $versioned, $type);

        return $this;
    }

    public function setMetaAt(string $key, mixed $value, DateTimeInterface|string $at, ?string $type = null): static
    {
        app(MetaManager::class)->setAt($this, $key, $value, $at, $type);

        return $this;
    }

    /**
     * @param  array<mixed, mixed>  $values
     */
    public function setMetas(array $values): static
    {
        app(MetaManager::class)->setMany($this, $values);

        return $this;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return app(MetaManager::class)->get($this, $key, $default);
    }

    public function getMetaAt(string $key, DateTimeInterface|string $at, mixed $default = null): mixed
    {
        return app(MetaManager::class)->getAt($this, $key, $at, $default);
    }

    public function hasMeta(string $key): bool
    {
        return app(MetaManager::class)->has($this, $key);
    }

    public function deleteMeta(string $key): bool
    {
        return app(MetaManager::class)->delete($this, $key);
    }

    public function metaHistory(string $key): Collection
    {
        return app(MetaManager::class)->history($this, $key);
    }

    public function refreshMeta(): static
    {
        app(MetaManager::class)->refresh($this);

        return $this;
    }

    public function scopeWhereMeta(Builder $query, string $key, string $operator, mixed $value, ?string $type = null): Builder
    {
        return app(MetaQuery::class)->whereMeta($query, $key, $operator, $value, $type);
    }

    public function scopeOrWhereMeta(Builder $query, string $key, string $operator, mixed $value, ?string $type = null): Builder
    {
        return $query->orWhere(function (Builder $inner) use ($key, $operator, $value, $type): void {
            $inner->whereMeta($key, $operator, $value, type: $type);
        });
    }

    public function scopeWhereMetaAt(Builder $query, string $key, string $operator, mixed $value, DateTimeInterface|string $at, ?string $type = null): Builder
    {
        return app(MetaQuery::class)->whereMetaAt($query, $key, $operator, $value, $at, $type);
    }

    /**
     * @param  list<mixed>  $values
     */
    public function scopeWhereMetaIn(Builder $query, string $key, array $values, ?string $type = null): Builder
    {
        return app(MetaQuery::class)->whereMetaIn($query, $key, $values, $type);
    }

    public function scopeWhereMetaBetween(Builder $query, string $key, mixed $min, mixed $max, ?string $type = null): Builder
    {
        return app(MetaQuery::class)->whereMetaBetween($query, $key, $min, $max, $type);
    }

    public function scopeWhereHasMeta(Builder $query, string $key): Builder
    {
        return app(MetaQuery::class)->whereHasMeta($query, $key);
    }

    public function scopeWhereDoesntHaveMeta(Builder $query, string $key): Builder
    {
        return app(MetaQuery::class)->whereDoesntHaveMeta($query, $key);
    }

    public function scopeWhereMetaNull(Builder $query, string $key): Builder
    {
        return app(MetaQuery::class)->whereMetaNull($query, $key);
    }

    public function scopeWhereMetaNotNull(Builder $query, string $key): Builder
    {
        return app(MetaQuery::class)->whereMetaNotNull($query, $key);
    }

    public function scopeWhereMetaLike(Builder $query, string $key, string $pattern): Builder
    {
        return app(MetaQuery::class)->whereMetaLike($query, $key, $pattern);
    }

    public function scopeWhereMetaJsonContains(Builder $query, string $key, mixed $fragment): Builder
    {
        return app(MetaQuery::class)->whereMetaJsonContains($query, $key, $fragment);
    }

    /**
     * @param  list<string>|null  $keys
     */
    public function scopeWithMeta(Builder $query, ?array $keys = null): Builder
    {
        if ($keys !== null) {
            foreach ($keys as $key) {
                app(MetaManager::class)->service()->assertKey($query->getModel(), (string) $key);
            }
        }

        return $query->afterQuery(function ($models) use ($keys) {
            if ($models instanceof \Illuminate\Support\Collection && $models->isNotEmpty()) {
                app(MetaManager::class)->load($models->all(), $keys);
            }

            return $models;
        });
    }

    protected static function purgesMetaOnDelete(Model $model): bool
    {
        if (config('meta.purge_on_force_delete') !== true) {
            return false;
        }

        if (method_exists($model, 'isForceDeleting')) {
            return (bool) $model->isForceDeleting();
        }

        return true;
    }
}
