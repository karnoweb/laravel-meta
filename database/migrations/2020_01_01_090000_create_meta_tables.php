<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metas', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_key', 64)->default('');
            $table->string('metable_type', 150);
            $table->string('metable_id', 64);
            $table->string('key', 120);
            $table->boolean('versioned')->default(false);
            $table->string('value_type', 16)->nullable();
            $table->string('value_string', 512)->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->decimal('value_decimal', 30, 10)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime', 6)->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps(6);

            $table->unique(
                ['tenant_key', 'metable_type', 'metable_id', 'key'],
                'metas_owner_key_unique',
            );
            $table->index(
                ['tenant_key', 'metable_type', 'metable_id'],
                'metas_owner_index',
            );
            $table->index(
                ['tenant_key', 'key', 'value_type', 'value_string'],
                'metas_string_lookup_index',
            );
            $table->index(
                ['tenant_key', 'key', 'value_type', 'value_integer'],
                'metas_integer_lookup_index',
            );
            $table->index(
                ['tenant_key', 'key', 'value_type', 'value_decimal'],
                'metas_decimal_lookup_index',
            );
        });

        Schema::create('meta_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meta_id')->constrained('metas')->cascadeOnDelete();
            $table->dateTime('effective_at', 6);
            $table->boolean('is_deleted')->default(false);
            $table->string('value_type', 16)->nullable();
            $table->string('value_string', 512)->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->decimal('value_decimal', 30, 10)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime', 6)->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps(6);

            $table->index(
                ['meta_id', 'effective_at', 'id'],
                'meta_versions_resolution_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_versions');
        Schema::dropIfExists('metas');
    }
};
