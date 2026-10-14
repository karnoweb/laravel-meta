<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Fixtures;

use Karnoweb\Meta\Traits\HasMeta;
use Illuminate\Database\Eloquent\Model;

class RestrictedProduct extends Model
{
    use HasMeta;

    protected $table = 'products';

    protected $guarded = [];

    protected array $metaKeys = ['color', 'visible'];
}
