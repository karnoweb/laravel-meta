<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Services;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Karnoweb\Meta\Exceptions\MetaException;
use Karnoweb\Meta\Exceptions\MetaVersionException;
use Karnoweb\Meta\Exceptions\UnsavedModelException;
use Karnoweb\Meta\Models\Meta;
use Karnoweb\Meta\Models\MetaVersion;
use Karnoweb\Meta\Support\MetaClock;
use Karnoweb\Meta\Support\MetaValue;
use Throwable;
use WeakMap;

class MetaService
{
    /** @var WeakMap<Model, array{tenant: string, complete: bool, keys: array<string, array{present: bool, value: mixed}>}> */
    private WeakMap $cache;

    public function __construct()
    {
        $this->cache = new WeakMap;
    }

    public function set(Model $owner, string $key, mixed $value, ?bool $versioned = null, ?string $type = null): Model
    {
        $this->assertPersisted($owner);
        $this->assertKey($owner, $key);
        $encoded = $this->encode($value, $type);

        $this->transaction($owner, function () use ($owner, $key, $encoded, $versioned): void {
            $meta = $this->lockOrCreate($owner, $key);
            $now = MetaClock::now();

            if ($meta->wasRecentlyCreated) {
                if ($versioned === true) {
                    $this->markVersioned($meta);
                    $this->appendVersion($meta, $encoded, $now);
                } else {
                    $this->writeCurrent($meta, $encoded);
                }

                return;
            }

            if ($meta->versioned) {
                if ($versioned === false) {
                    throw new MetaVersionException("Meta key [{$key}] is versioned and cannot be switched off.");
                }

                $this->appendVersion($meta, $encoded, $now);

                return;
            }

            if ($versioned === true) {
                $this->promote($meta, $now);
                $this->appendVersion($meta, $encoded, $now);

                return;
            }

            $this->writeCurrent($meta, $encoded);
        });

        $this->forget($owner);

        return $owner;
    }

    public function setAt(Model $owner, string $key, mixed $value, DateTimeInterface|string $at, ?string $type = null): Model
    {
        $this->assertPersisted($owner);
        $this->assertKey($owner, $key);
        $instant = MetaClock::parse($at);
        $encoded = $this->encode($value, $type);

        $this->transaction($owner, function () use ($owner, $key, $encoded, $instant): void {
            $meta = $this->lockOrCreate($owner, $key);
            $now = MetaClock::now();

            if ($meta->wasRecentlyCreated) {
                $this->markVersioned($meta);
            } elseif (! $meta->versioned) {
                if ($instant->lessThan($now)) {
                    throw new MetaVersionException('Cannot backdate the promotion of an unversioned key. The previous value has no history before the promotion instant.');
                }

                $this->promote($meta, $now);
            }

            $this->appendVersion($meta, $encoded, $instant);
        });

        $this->forget($owner);

        return $owner;
    }

    /**
     * @param  array<mixed, mixed>  $values
     */
    public function setMany(Model $owner, array $values): Model
    {
        $this->assertPersisted($owner);

        $normalized = [];

        foreach ($values as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        ksort($normalized, SORT_STRING);

        foreach (array_keys($normalized) as $key) {
            $this->assertKey($owner, $key);
        }

        $this->transaction($owner, function () use ($owner, $normalized): void {
            foreach ($normalized as $key => $value) {
                $encoded = $this->encode($value, null);
                $meta = $this->lockOrCreate($owner, $key);
                $now = MetaClock::now();

                if ($meta->wasRecentlyCreated || ! $meta->versioned) {
                    $this->writeCurrent($meta, $encoded);

                    continue;
                }

                $this->appendVersion($meta, $encoded, $now);
            }
        });

        $this->forget($owner);

        return $owner;
    }

    public function get(Model $owner, string $key, mixed $default = null): mixed
    {
        $this->assertPersisted($owner);
        $this->assertKey($owner, $key);

        $cached = $this->cached($owner, $key);

        if ($cached !== null) {
            return $cached['present'] ? $cached['value'] : $default;
        }

        $meta = $this->find($owner, $key);

        if ($meta === null) {
            return $default;
        }

        if (! $meta->versioned) {
            return MetaValue::decode($meta);
        }

        $current = $this->currentVersion($meta, MetaClock::now());

        if ($current === null || $current->is_deleted) {
            return $default;
        }

        return $current->value();
    }

    public function getAt(Model $owner, string $key, DateTimeInterface|string $at, mixed $default = null): mixed
    {
        $this->assertPersisted($owner);
        $this->assertKey($owner, $key);

        $meta = $this->find($owner, $key);

        if ($meta === null || ! $meta->versioned) {
            throw new MetaVersionException("Meta key [{$key}] is not versioned. getMetaAt() does not invent history for a current-only value.");
        }

        $current = $this->currentVersion($meta, MetaClock::parse($at));

        if ($current === null || $current->is_deleted) {
            return $default;
        }

        return $current->value();
    }

    public function has(Model $owner, string $key): bool
    {
        $this->assertPersisted($owner);
        $this->assertKey($owner, $key);

        $cached = $this->cached($owner, $key);

        if ($cached !== null) {
            return $cached['present'];
        }

        $meta = $this->find($owner, $key);

        if ($meta === null) {
            return false;
        }

        if (! $meta->versioned) {
            return true;
        }

        $current = $this->currentVersion($meta, MetaClock::now());

        return $current !== null && ! $current->is_deleted;
    }

    public function delete(Model $owner, string $key): bool
    {
        $this->assertPersisted($owner);
        $this->assertKey($owner, $key);

        $deleted = $this->transaction($owner, function () use ($owner, $key): bool {
            $meta = $this->query($owner)->where($this->identity($owner, $key))->lockForUpdate()->first();

            if ($meta === null) {
                return false;
            }

            if (! $meta->versioned) {
                $meta->delete();

                return true;
            }

            $current = $this->currentVersion($meta, MetaClock::now());

            if ($current === null || $current->is_deleted) {
                return false;
            }

            $this->appendTombstone($meta, MetaClock::now());

            return true;
        });

        $this->forget($owner);

        return $deleted;
    }

    public function history(Model $owner, string $key): EloquentCollection
    {
        $this->assertPersisted($owner);
        $this->assertKey($owner, $key);

        $meta = $this->find($owner, $key);

        if ($meta === null || ! $meta->versioned) {
            return new EloquentCollection;
        }

        return MetaVersion::on($meta->getConnectionName())
            ->where('meta_id', $meta->getKey())
            ->orderBy('effective_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  iterable<Model>  $models
     * @param  list<string>|null  $keys
     */
    public function load(iterable $models, ?array $keys = null): void
    {
        $owners = [];

        foreach ($models as $model) {
            if (! $model instanceof Model || ! $model->exists || $model->getKey() === null) {
                continue;
            }

            $owners[] = $model;
        }

        if ($owners === []) {
            return;
        }

        if ($keys !== null) {
            $keys = array_values($keys);

            foreach ($owners as $owner) {
                foreach ($keys as $key) {
                    $this->assertKey($owner, $key);
                }
            }
        }

        $tenant = $this->tenantKey();
        $now = MetaClock::now();
        $groups = [];

        foreach ($owners as $owner) {
            $bucket = $owner->getConnectionName().'|'.$owner->getMorphClass();
            $groups[$bucket][] = $owner;
        }

        foreach ($groups as $group) {
            $this->loadGroup($group, $keys, $tenant, $now);
        }
    }

    public function refresh(Model $owner): void
    {
        $this->assertPersisted($owner);

        $state = $this->cache[$owner] ?? null;
        $keys = null;
        $complete = false;

        if (is_array($state)) {
            $complete = $state['complete'];
            $keys = $complete ? null : array_keys($state['keys']);
        }

        $this->forget($owner);

        if ($state !== null) {
            $this->load([$owner], $keys);
        }
    }

    public function purge(Model $owner): int
    {
        return $this->purgeMany([$owner]);
    }

    /**
     * @param  iterable<Model>  $models
     */
    public function purgeMany(iterable $models): int
    {
        $groups = [];

        foreach ($models as $model) {
            if (! $model instanceof Model) {
                continue;
            }

            $id = $model->getKey();

            if ($id === null || $id === '') {
                throw new UnsavedModelException('Meta purge requires a model primary key.');
            }

            $ownerId = $this->ownerId($model);
            $bucket = ($model->getConnectionName() ?? '').'|'.$model->getMorphClass();
            $groups[$bucket]['model'] = $model;
            $groups[$bucket]['ids'][$ownerId] = $ownerId;
            $groups[$bucket]['instances'][] = $model;
        }

        if ($groups === []) {
            return 0;
        }

        $tenant = $this->tenantKey();
        $deleted = 0;

        foreach ($groups as $group) {
            /** @var Model $prototype */
            $prototype = $group['model'];
            $ids = array_values($group['ids']);
            $metaIds = $this->query($prototype)
                ->where('tenant_key', $tenant)
                ->where('metable_type', $prototype->getMorphClass())
                ->whereIn('metable_id', $ids)
                ->pluck('id');

            if ($metaIds->isEmpty()) {
                continue;
            }

            $connection = $prototype->getConnectionName();
            MetaVersion::on($connection)->whereIn('meta_id', $metaIds->all())->delete();
            $deleted += Meta::on($connection)->whereIn('id', $metaIds->all())->delete();

            foreach ($group['instances'] as $instance) {
                $this->forget($instance);
            }
        }

        return $deleted;
    }

    public function tenantKey(): string
    {
        $enabled = config('meta.tenant.enabled');

        if ($enabled === null) {
            $enabled = false;
        }

        if ($enabled !== true) {
            return '';
        }

        $resolver = config('meta.tenant.resolver');

        if (! is_string($resolver) || $resolver === '') {
            throw new MetaException('Tenant resolution is enabled but meta.tenant.resolver is missing. Refusing to fall back to the public tenant.');
        }

        if (! app()->bound($resolver) && ! class_exists($resolver)) {
            throw new MetaException("Tenant resolver [{$resolver}] cannot be resolved.");
        }

        $instance = app()->make($resolver);

        if (! is_callable($instance)) {
            throw new MetaException("Tenant resolver [{$resolver}] is not invokable.");
        }

        $key = $instance();

        if (! is_string($key) || $key === '' || mb_strlen($key) > 64 || preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new MetaException('Tenant resolver returned an invalid tenant key. A non-empty string of at most 64 characters is required.');
        }

        return $key;
    }

    public function assertKey(Model $owner, string $key): void
    {
        $max = config('meta.max_key_length');
        $max = is_int($max) ? $max : 120;

        if ($key === '' || ! mb_check_encoding($key, 'UTF-8') || mb_strlen($key) > $max || preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new MetaException('Meta key is empty, too long, or contains control characters.');
        }

        if (method_exists($owner, 'metaAllowedKeys')) {
            $allowed = $owner->metaAllowedKeys();

            if (is_array($allowed) && ! in_array($key, $allowed, true)) {
                throw new MetaException("Meta key [{$key}] is not allowed on ".$owner::class.'.');
            }
        }
    }

    public function maxString(): int
    {
        $value = config('meta.max_string_length');

        return is_int($value) ? $value : 512;
    }

    public function maxJson(): int
    {
        $value = config('meta.max_json_bytes');

        return is_int($value) ? $value : 1048576;
    }

    private function assertPersisted(Model $owner): void
    {
        if (! $owner->exists || $owner->getKey() === null) {
            throw new UnsavedModelException('Meta can only be used on a saved model. The package does not call save() for you.');
        }

        $this->ownerId($owner);
        $this->assertMorph($owner);
    }

    private function ownerId(Model $owner): string
    {
        $id = (string) $owner->getKey();

        if ($id === '' || mb_strlen($id) > 64) {
            throw new MetaException('The model key must be a non-empty string of at most 64 characters. It is not truncated.');
        }

        return $id;
    }

    private function assertMorph(Model $owner): void
    {
        $morph = $owner->getMorphClass();

        if ($morph === '' || mb_strlen($morph) > 150) {
            throw new MetaException('The morph class must be a non-empty string of at most 150 characters.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function identity(Model $owner, string $key): array
    {
        return [
            'tenant_key' => $this->tenantKey(),
            'metable_type' => $owner->getMorphClass(),
            'metable_id' => $this->ownerId($owner),
            'key' => $key,
        ];
    }

    private function query(Model $owner): \Illuminate\Database\Eloquent\Builder
    {
        return Meta::on($owner->getConnectionName())->newQuery();
    }

    private function find(Model $owner, string $key): ?Meta
    {
        return $this->query($owner)->where($this->identity($owner, $key))->first();
    }

    private function lockOrCreate(Model $owner, string $key): Meta
    {
        $identity = $this->identity($owner, $key);
        $meta = $this->query($owner)->where($identity)->lockForUpdate()->first();

        if ($meta !== null) {
            return $meta;
        }

        try {
            $created = null;
            $owner->getConnection()->transaction(function () use ($owner, $identity, &$created): void {
                $created = new Meta;
                $created->setConnection($owner->getConnectionName());
                $created->forceFill($identity);
                $created->setAttribute('versioned', false);
                MetaValue::clear($created);
                $created->save();
            });

            if (! $created instanceof Meta) {
                throw new MetaException('Failed to create the meta row.');
            }

            return $created;
        } catch (Throwable $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }
        }

        $meta = $this->query($owner)->where($identity)->lockForUpdate()->first();

        if ($meta === null) {
            throw new MetaException('Failed to resolve the meta row after a concurrent insert.');
        }

        return $meta;
    }

    /**
     * @param  array{value_type: string, columns: array<string, mixed>}  $encoded
     */
    private function writeCurrent(Meta $meta, array $encoded): void
    {
        $meta->setAttribute('versioned', false);
        MetaValue::apply($meta, $encoded);
        $meta->save();
    }

    private function markVersioned(Meta $meta): void
    {
        $meta->setAttribute('versioned', true);
        MetaValue::clear($meta);
        $meta->save();
    }

    private function promote(Meta $meta, CarbonImmutable $at): void
    {
        $version = new MetaVersion;
        $version->setConnection($meta->getConnectionName());
        $version->setAttribute('meta_id', $meta->getKey());
        $version->setAttribute('effective_at', MetaClock::format($at));
        $version->setAttribute('is_deleted', false);
        $version->setAttribute('value_type', $meta->getAttributes()['value_type'] ?? null);

        foreach (MetaValue::COLUMNS as $column) {
            $version->setAttribute($column, $meta->getAttributes()[$column] ?? null);
        }

        $version->save();
        $this->markVersioned($meta);
    }

    /**
     * @param  array{value_type: string, columns: array<string, mixed>}  $encoded
     */
    private function appendVersion(Meta $meta, array $encoded, CarbonImmutable $at): void
    {
        $version = new MetaVersion;
        $version->setConnection($meta->getConnectionName());
        $version->setAttribute('meta_id', $meta->getKey());
        $version->setAttribute('effective_at', MetaClock::format($at));
        $version->setAttribute('is_deleted', false);
        MetaValue::apply($version, $encoded);
        $version->save();
    }

    private function appendTombstone(Meta $meta, CarbonImmutable $at): void
    {
        $version = new MetaVersion;
        $version->setConnection($meta->getConnectionName());
        $version->setAttribute('meta_id', $meta->getKey());
        $version->setAttribute('effective_at', MetaClock::format($at));
        $version->setAttribute('is_deleted', true);
        MetaValue::clear($version);
        $version->save();
    }

    private function currentVersion(Meta $meta, CarbonImmutable $at): ?MetaVersion
    {
        return MetaVersion::on($meta->getConnectionName())
            ->where('meta_id', $meta->getKey())
            ->where('effective_at', '<=', MetaClock::format($at))
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  list<Model>  $owners
     * @param  list<string>|null  $keys
     */
    private function loadGroup(array $owners, ?array $keys, string $tenant, CarbonImmutable $now): void
    {
        $prototype = $owners[0];
        $ids = [];

        foreach ($owners as $owner) {
            $ids[$this->ownerId($owner)] = $this->ownerId($owner);
        }

        $metas = $this->query($prototype)
            ->where('tenant_key', $tenant)
            ->where('metable_type', $prototype->getMorphClass())
            ->whereIn('metable_id', array_values($ids))
            ->when($keys !== null, fn ($query) => $query->whereIn('key', $keys))
            ->get();

        $versionedIds = $metas->where('versioned', true)->pluck('id')->all();
        $currents = $versionedIds === []
            ? collect()
            : $this->latestVersions($prototype, $versionedIds, $now);

        $byOwner = [];

        foreach ($metas as $meta) {
            $byOwner[$meta->metable_id][$meta->key] = $meta;
        }

        foreach ($owners as $owner) {
            $rows = $byOwner[$this->ownerId($owner)] ?? [];
            $resolved = [];

            $knownKeys = $keys ?? array_keys($rows);

            foreach ($knownKeys as $key) {
                $meta = $rows[$key] ?? null;

                if (! $meta instanceof Meta) {
                    $resolved[$key] = ['present' => false, 'value' => null];

                    continue;
                }

                if (! $meta->versioned) {
                    $resolved[$key] = ['present' => true, 'value' => MetaValue::decode($meta)];

                    continue;
                }

                $current = $currents->get($meta->getKey());

                if (! $current instanceof MetaVersion || $current->is_deleted) {
                    $resolved[$key] = ['present' => false, 'value' => null];

                    continue;
                }

                $resolved[$key] = ['present' => true, 'value' => $current->value()];
            }

            $this->cache[$owner] = [
                'tenant' => $tenant,
                'complete' => $keys === null,
                'keys' => $resolved,
            ];

            $owner->unsetRelation('metas');
        }
    }

    /**
     * @param  list<int|string>  $metaIds
     * @return Collection<int|string, MetaVersion>
     */
    private function latestVersions(Model $owner, array $metaIds, CarbonImmutable $at): Collection
    {
        $connection = $owner->getConnection();
        $grammar = $connection->getQueryGrammar();
        $table = $grammar->wrapTable('meta_versions');
        $placeholders = implode(', ', array_fill(0, count($metaIds), '?'));
        $sql = 'select * from (select '.$table.'.*, row_number() over (partition by meta_id order by effective_at desc, id desc) as meta_rn from '.$table.' where meta_id in ('.$placeholders.') and effective_at <= ?) ranked where meta_rn = 1';
        $rows = $connection->select($sql, [...array_values($metaIds), MetaClock::format($at)]);
        $hydrated = [];

        foreach ($rows as $row) {
            $attributes = (array) $row;
            unset($attributes['meta_rn']);
            $hydrated[] = $attributes;
        }

        return MetaVersion::on($owner->getConnectionName())
            ->hydrate($hydrated)
            ->keyBy('meta_id');
    }

    /**
     * @return array{present: bool, value: mixed}|null
     */
    private function cached(Model $owner, string $key): ?array
    {
        if (! isset($this->cache[$owner])) {
            return null;
        }

        $state = $this->cache[$owner];

        if ($state['tenant'] !== $this->tenantKey()) {
            $this->forget($owner);

            return null;
        }

        if (array_key_exists($key, $state['keys'])) {
            return $state['keys'][$key];
        }

        if ($state['complete']) {
            return ['present' => false, 'value' => null];
        }

        return null;
    }

    private function forget(Model $owner): void
    {
        unset($this->cache[$owner]);
        $owner->unsetRelation('metas');
    }

    /**
     * @return array{value_type: string, columns: array<string, mixed>}
     */
    private function encode(mixed $value, ?string $type): array
    {
        return MetaValue::encode($value, $type, $this->maxString(), $this->maxJson());
    }

    private function transaction(Model $owner, Closure $callback): mixed
    {
        $attempt = 0;

        while (true) {
            try {
                return $owner->getConnection()->transaction($callback);
            } catch (Throwable $exception) {
                if (! $this->isDeadlock($exception) || $attempt >= 2) {
                    throw $exception;
                }

                $attempt++;
                usleep(20000 * $attempt);
            }
        }
    }

    private function isDeadlock(Throwable $exception): bool
    {
        if ($exception instanceof \Illuminate\Database\DeadlockException) {
            return true;
        }

        if (! $exception instanceof QueryException) {
            return false;
        }

        $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($state, ['40001', '40P01'], true);
    }

    private function isUniqueViolation(Throwable $exception): bool
    {
        if ($exception instanceof UniqueConstraintViolationException) {
            return true;
        }

        if (! $exception instanceof QueryException) {
            return false;
        }

        $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($state, ['23000', '23505'], true);
    }
}
