<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Meta\Traits\HasMeta;

class UuidRecord extends Model
{
    use HasMeta;

    public $incrementing = false;

    protected $table = 'uuid_records';

    protected $keyType = 'string';

    protected $guarded = [];
}
