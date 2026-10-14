<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Fixtures;

final class StaticTenantResolver
{
    public static ?string $key = 'tenant-a';

    public function __invoke(): ?string
    {
        return self::$key;
    }
}
