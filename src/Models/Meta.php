<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Karnoweb\Meta\Support\MetaValue;

class Meta extends Model
{
    protected $table = 'metas';

    protected $guarded = [];

    protected $casts = [
        'versioned' => 'boolean',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(MetaVersion::class, 'meta_id');
    }

    public function value(): mixed
    {
        return MetaValue::decode($this);
    }
}
