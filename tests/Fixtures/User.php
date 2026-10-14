<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Meta\Traits\HasMeta;

class User extends Model
{
    use HasMeta;

    protected $table = 'users';

    protected $guarded = [];
}
