<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Services;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Karnoweb\Meta\Exceptions\MetaQueryException;
use Karnoweb\Meta\Support\MetaClock;
use Karnoweb\Meta\Support\MetaType;
use Karnoweb\Meta\Support\MetaValue;

class MetaQuery
{
    /** @var list<string> */
    private const OPERATORS = ['=', '!=', '<>', '>', '>=', '<', '<='];

    private int $alias = 0;

    public function __construct(private readonly MetaService $metas) {}

    public function whereMeta(Builder $query, string $key, string $operator, mixed $value, ?string $type = null): Builder
    {
        $binding = $this->binding($value, $type);
        $this->assertOperator($operator, $binding['type']);

        return $this->exists($query, $key, null, false, function (QueryBuilder $row, string $alias) use ($binding, $operator): void {
            $this->applyComparison($row, $alias, $binding, $operator);
        });
    }

    public function whereMetaAt(Builder $query, string $key, string $operator, mixed $value, DateTimeInterface|string $at, ?string $type = null): Builder
    {
        $binding = $this->binding($value, $type);
        $this->assertOperator($operator, $binding['type']);

        return $this->exists($query, $key, MetaClock::parse($at), true, function (QueryBuilder $row, string $alias) use ($binding, $operator): void {
            $this->applyComparison($row, $alias, $binding, $operator);
        });
    }

    /**
     * @param  list<mixed>  $values
     */
    public function whereMetaIn(Builder $query, string $key, array $values, ?string $type = null): Builder
    {
        $this->prepare($query, $key);

        if ($values === []) {
            return $query->whereRaw('1 = 0');
        }

        $groups = [];
        $nulls = false;

        foreach ($values as $value) {
            if ($value === null) {
                if ($type !== null && $type !== MetaType::NULL) {
                    throw new MetaQueryException('A null member of whereMetaIn() does not match the declared type.');
                }

                $nulls = true;

                continue;
            }

            $binding = $this->binding($value, $type);
            $groups[$binding['type']]['column'] = $binding['column'];
            $groups[$binding['type']]['values'][] = $binding['stored'];

            if ($binding['guard'] !== null) {
                $groups[$binding['type']]['guard_column'] = $binding['guard']['column'];
                $groups[$binding['type']]['guard_values'][] = $binding['guard']['stored'];
            }
        }

        return $this->exists($query, $key, null, false, function (QueryBuilder $row, string $alias) use ($groups, $nulls): void {
            $row->where(function (QueryBuilder $grouped) use ($alias, $groups, $nulls): void {
                $first = true;

                foreach ($groups as $metaType => $group) {
                    $apply = function (QueryBuilder $one) use ($alias, $metaType, $group): void {
                        $one->where($alias.'.value_type', '=', $metaType)
                            ->whereIn($alias.'.'.$group['column'], $group['values']);

                        if (isset($group['guard_column'])) {
                            $one->whereIn($alias.'.'.$group['guard_column'], $group['guard_values']);
                        }
                    };

                    if ($first) {
                        $grouped->where($apply);
                        $first = false;
                    } else {
                        $grouped->orWhere($apply);
                    }
                }

                if ($nulls) {
                    $nullMatch = fn (QueryBuilder $one) => $one->where($alias.'.value_type', '=', MetaType::NULL);

                    if ($first) {
                        $grouped->where($nullMatch);
                    } else {
                        $grouped->orWhere($nullMatch);
                    }
                }
            });
        });
    }

    public function whereMetaBetween(Builder $query, string $key, mixed $min, mixed $max, ?string $type = null): Builder
    {
        $left = $this->binding($min, $type);
        $right = $this->binding($max, $type);

        if ($left['type'] !== $right['type']) {
            throw new MetaQueryException('whereMetaBetween() bounds must be the same meta type.');
        }

        if (! in_array($left['type'], MetaType::COMPARABLE, true)) {
            throw new MetaQueryException('whereMetaBetween() only accepts int, decimal, date, and datetime values.');
        }

        $this->prepare($query, $key);

        if (MetaValue::compare($left['type'], $left['compare'], $right['compare']) > 0) {
            return $query->whereRaw('1 = 0');
        }

        return $this->exists($query, $key, null, false, function (QueryBuilder $row, string $alias) use ($left, $right): void {
            $row->where($alias.'.value_type', '=', $left['type'])
                ->where($alias.'.'.$left['column'], '>=', $left['stored'])
                ->where($alias.'.'.$left['column'], '<=', $right['stored']);
        });
    }

    public function whereMetaLike(Builder $query, string $key, string $pattern): Builder
    {
        if (! mb_check_encoding($pattern, 'UTF-8')) {
            throw new MetaQueryException('LIKE patterns must be valid UTF-8.');
        }

        return $this->exists($query, $key, null, false, function (QueryBuilder $row, string $alias) use ($pattern): void {
            $row->where($alias.'.value_type', '=', MetaType::STRING)
                ->where($alias.'.value_string', 'like', $pattern);
        });
    }

    public function whereMetaJsonContains(Builder $query, string $key, mixed $fragment): Builder
    {
        if (is_object($fragment)) {
            throw new MetaQueryException('whereMetaJsonContains() rejects PHP objects.');
        }

        try {
            json_encode($fragment, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new MetaQueryException('whereMetaJsonContains() requires a JSON-serializable fragment.');
        }

        if (is_array($fragment)) {
            $this->prepare($query, $key);

            if ($query->getConnection()->getDriverName() === 'sqlite') {
                throw new MetaQueryException('whereMetaJsonContains() on SQLite accepts only a top-level array element. Nested containment is rejected instead of returning an empty result. Use MySQL or PostgreSQL for nested JSON.');
            }
        }

        return $this->exists($query, $key, null, false, function (QueryBuilder $row, string $alias) use ($fragment): void {
            $row->where($alias.'.value_type', '=', MetaType::JSON)
                ->whereJsonContains($alias.'.value_json', $fragment);
        });
    }

    public function whereHasMeta(Builder $query, string $key): Builder
    {
        return $this->exists($query, $key, null, false, null);
    }

    public function whereDoesntHaveMeta(Builder $query, string $key): Builder
    {
        return $this->exists($query, $key, null, false, null, true);
    }

    public function whereMetaNull(Builder $query, string $key): Builder
    {
        return $this->exists($query, $key, null, false, function (QueryBuilder $row, string $alias): void {
            $row->where($alias.'.value_type', '=', MetaType::NULL);
        });
    }

    public function whereMetaNotNull(Builder $query, string $key): Builder
    {
        return $this->exists($query, $key, null, false, function (QueryBuilder $row, string $alias): void {
            $row->where($alias.'.value_type', '!=', MetaType::NULL);
        });
    }

    private function binding(mixed $value, ?string $type): array
    {
        return MetaValue::binding($value, $type, $this->metas->maxString(), $this->metas->maxJson());
    }

    /**
     * @param  array{type: string, column: string, stored: mixed, compare: mixed, guard: array{column: string, stored: mixed}|null}  $binding
     */
    private function applyComparison(QueryBuilder $row, string $alias, array $binding, string $operator): void
    {
        $row->where($alias.'.value_type', '=', $binding['type']);
        $column = $alias.'.'.$binding['column'];

        if ($binding['guard'] === null) {
            $row->where($column, $this->sqlOperator($operator), $binding['stored']);

            return;
        }

        $guardColumn = $alias.'.'.$binding['guard']['column'];
        $guardValue = $binding['guard']['stored'];

        if (in_array($operator, ['!=', '<>'], true)) {
            $row->where(function (QueryBuilder $mismatch) use ($column, $binding, $guardColumn, $guardValue): void {
                $mismatch->where($column, '<>', $binding['stored'])
                    ->orWhere($guardColumn, '<>', $guardValue);
            });

            return;
        }

        $row->where($column, '=', $binding['stored'])
            ->where($guardColumn, '=', $guardValue);
    }

    private function assertOperator(string $operator, string $type): void
    {
        if (! in_array($operator, self::OPERATORS, true)) {
            throw new MetaQueryException("Operator [{$operator}] is not allowed.");
        }

        if (in_array($type, [MetaType::STRING, MetaType::BOOL], true) && ! in_array($operator, ['=', '!=', '<>'], true)) {
            throw new MetaQueryException("Operator [{$operator}] is not valid for {$type} meta.");
        }
    }

    private function sqlOperator(string $operator): string
    {
        return $operator === '!=' ? '<>' : $operator;
    }

    private function prepare(Builder $query, string $key): void
    {
        $this->metas->assertKey($query->getModel(), $key);
        $this->metas->branchKey();
    }

    private function exists(
        Builder $query,
        string $key,
        ?CarbonImmutable $asOf,
        bool $versionedOnly,
        ?Closure $predicate,
        bool $negate = false,
    ): Builder {
        $this->prepare($query, $key);

        $model = $query->getModel();
        $branch = $this->metas->branchKey();
        $morph = $model->getMorphClass();
        $asOf ??= MetaClock::now();
        $asOfValue = MetaClock::format($asOf);
        $cast = $this->ownerKeyCast($query);
        $method = $negate ? 'whereNotExists' : 'whereExists';

        $query->{$method}(function (QueryBuilder $meta) use ($model, $branch, $morph, $key, $cast, $predicate, $versionedOnly, $asOfValue): void {
            $meta->selectRaw('1')
                ->from('metas')
                ->where('metas.branch_key', '=', $branch)
                ->where('metas.metable_type', '=', $morph)
                ->whereRaw('metas.metable_id = '.$cast)
                ->where('metas.key', '=', $key)
                ->where(function (QueryBuilder $mode) use ($predicate, $versionedOnly, $asOfValue): void {
                    if (! $versionedOnly) {
                        $mode->where(function (QueryBuilder $current) use ($predicate): void {
                            $current->where('metas.versioned', '=', 0);

                            if ($predicate !== null) {
                                $predicate($current, 'metas');
                            }
                        });
                    }

                    $versioned = function (QueryBuilder $versioned) use ($predicate, $asOfValue): void {
                        $versioned->where('metas.versioned', '=', 1)
                            ->whereExists(function (QueryBuilder $version) use ($predicate, $asOfValue): void {
                                $version->selectRaw('1')
                                    ->from('meta_versions')
                                    ->whereColumn('meta_versions.meta_id', 'metas.id')
                                    ->where('meta_versions.is_deleted', '=', 0)
                                    ->whereRaw('meta_versions.effective_at = ('.$this->maxEffectiveSql().')', [$asOfValue])
                                    ->whereRaw('meta_versions.id = ('.$this->maxIdSql().')');

                                if ($predicate !== null) {
                                    $predicate($version, 'meta_versions');
                                }
                            });
                    };

                    if ($versionedOnly) {
                        $mode->where($versioned);
                    } else {
                        $mode->orWhere($versioned);
                    }
                });
        });

        return $query;
    }

    private function maxEffectiveSql(): string
    {
        $this->alias++;
        $alias = 'mv_time_'.$this->alias;

        return 'select max('.$alias.'.effective_at) from meta_versions as '.$alias
            .' where '.$alias.'.meta_id = meta_versions.meta_id and '.$alias.'.effective_at <= ?';
    }

    private function maxIdSql(): string
    {
        $this->alias++;
        $alias = 'mv_id_'.$this->alias;

        return 'select max('.$alias.'.id) from meta_versions as '.$alias
            .' where '.$alias.'.meta_id = meta_versions.meta_id and '.$alias.'.effective_at = meta_versions.effective_at';
    }

    private function ownerKeyCast(Builder $query): string
    {
        $wrapped = $query->getGrammar()->wrap($query->getModel()->getQualifiedKeyName());
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return 'cast('.$wrapped.' as char)';
        }

        return 'cast('.$wrapped.' as text)';
    }
}
