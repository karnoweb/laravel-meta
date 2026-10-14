<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Fixtures;

final class StaticBranchResolver
{
    public static ?string $key = 'branch-a';

    public function __invoke(): ?string
    {
        return self::$key;
    }
}
