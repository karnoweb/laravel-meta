<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Meta\Traits\HasMeta;

class Product extends Model
{
    use HasMeta;

    protected $table = 'products';

    protected $guarded = [];
}
