<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karnoweb\Meta\Exceptions\MetaVersionException;
use Karnoweb\Meta\Support\MetaValue;

class MetaVersion extends Model
{
    protected $table = 'meta_versions';

    protected $guarded = [];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new MetaVersionException('Meta versions are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new MetaVersionException('Individual meta versions cannot be deleted.');
        });
    }

    public function meta(): BelongsTo
    {
        return $this->belongsTo(Meta::class, 'meta_id');
    }

    public function value(): mixed
    {
        if ($this->is_deleted) {
            return null;
        }

        return MetaValue::decode($this);
    }
}
