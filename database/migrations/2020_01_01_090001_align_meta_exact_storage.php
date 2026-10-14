<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Meta\Exceptions\MetaTypeException;
use Karnoweb\Meta\Support\MetaValue;

/**
 * Upgrades databases that already ran the first published migration.
 * Fresh installs create the final columns in the base migration; this file then no-ops.
 * Decimal text backfilled from value_decimal cannot restore precision SQLite already dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->renameLegacyBranchColumn();
        $this->addExactColumns('metas');
        $this->addExactColumns('meta_versions');
        $this->alignBranchCollation();
        $this->alignIndexes();
        $this->backfill('metas');
        $this->backfill('meta_versions');
    }

    public function down(): void
    {
        $this->dropExactColumns('meta_versions');
        $this->dropExactColumns('metas');
    }

    private function addExactColumns(string $table): void
    {
        if (Schema::hasColumn($table, 'value_decimal_text')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $hash = $blueprint->char('value_string_hash', 64)->nullable();
            $sort = $blueprint->char('value_decimal_sort', 31)->nullable();

            if ($this->mysql()) {
                $hash->charset('ascii')->collation('ascii_bin');
                $sort->charset('ascii')->collation('ascii_bin');
            }

            $blueprint->string('value_decimal_text', 32)->nullable();
        });
    }

    private function dropExactColumns(string $table): void
    {
        if (! Schema::hasColumn($table, 'value_decimal_text')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['value_string_hash', 'value_decimal_sort', 'value_decimal_text']);
        });
    }

    private function renameLegacyBranchColumn(): void
    {
        if (! Schema::hasColumn('metas', 'tenant_key') || Schema::hasColumn('metas', 'branch_key')) {
            return;
        }

        Schema::table('metas', function (Blueprint $table): void {
            $table->renameColumn('tenant_key', 'branch_key');
        });
    }

    private function alignBranchCollation(): void
    {
        if (! $this->mysql()) {
            return;
        }

        $column = DB::selectOne("show full columns from metas where Field = 'branch_key'");
        $collation = strtolower((string) ($column->Collation ?? $column->collation ?? ''));

        if (str_contains($collation, '_bin')) {
            return;
        }

        Schema::table('metas', function (Blueprint $table): void {
            $table->dropUnique('metas_owner_key_unique');
        });

        DB::statement("alter table metas modify branch_key varchar(64) not null default '' collate utf8mb4_0900_bin");

        Schema::table('metas', function (Blueprint $table): void {
            $table->unique(
                ['branch_key', 'metable_type', 'metable_id', 'key'],
                'metas_owner_key_unique',
            );
        });
    }

    private function alignIndexes(): void
    {
        $indexes = collect(Schema::getIndexes('metas'))->keyBy('name');

        if ($indexes->has('metas_string_lookup_index')) {
            Schema::table('metas', function (Blueprint $table): void {
                $table->dropIndex('metas_string_lookup_index');
            });
        }

        $names = collect(Schema::getIndexes('metas'))->keyBy('name');

        if (! $names->has('metas_string_hash_index')) {
            Schema::table('metas', function (Blueprint $table): void {
                $table->index(
                    ['branch_key', 'key', 'value_type', 'value_string_hash'],
                    'metas_string_hash_index',
                );
            });
        }

        $decimal = $names->get('metas_decimal_lookup_index');
        $columns = $decimal['columns'] ?? [];

        if (! in_array('value_decimal_sort', $columns, true)) {
            Schema::table('metas', function (Blueprint $table): void {
                if (collect(Schema::getIndexes('metas'))->pluck('name')->contains('metas_decimal_lookup_index')) {
                    $table->dropIndex('metas_decimal_lookup_index');
                }

                $table->index(
                    ['branch_key', 'key', 'value_type', 'value_decimal_sort'],
                    'metas_decimal_lookup_index',
                );
            });
        }
    }

    private function backfill(string $table): void
    {
        DB::table($table)->where('value_type', 'string')->whereNull('value_string_hash')->orderBy('id')->chunkById(200, function ($rows) use ($table): void {
            foreach ($rows as $row) {
                DB::table($table)->where('id', $row->id)->update([
                    'value_string_hash' => hash('sha256', (string) $row->value_string),
                ]);
            }
        });

        DB::table($table)->where('value_type', 'decimal')->whereNull('value_decimal_text')->orderBy('id')->chunkById(200, function ($rows) use ($table): void {
            foreach ($rows as $row) {
                if ($row->value_decimal === null) {
                    continue;
                }

                try {
                    $canonical = MetaValue::normalizeDecimal((string) $row->value_decimal);
                } catch (MetaTypeException) {
                    continue;
                }

                DB::table($table)->where('id', $row->id)->update([
                    'value_decimal_text' => $canonical,
                    'value_decimal_sort' => MetaValue::decimalSortKey($canonical),
                ]);
            }
        });
    }

    private function mysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
