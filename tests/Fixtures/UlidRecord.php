<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Meta\Traits\HasMeta;

class UlidRecord extends Model
{
    use HasMeta;

    public $incrementing = false;

    protected $table = 'ulid_records';

    protected $keyType = 'string';

    protected $guarded = [];
}
