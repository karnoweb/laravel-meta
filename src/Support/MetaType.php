<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Support;

final class MetaType
{
    public const NULL = 'null';

    public const STRING = 'string';

    public const INT = 'int';

    public const DECIMAL = 'decimal';

    public const BOOL = 'bool';

    public const DATE = 'date';

    public const DATETIME = 'datetime';

    public const JSON = 'json';

    /** @var list<string> */
    public const ALL = [
        self::NULL,
        self::STRING,
        self::INT,
        self::DECIMAL,
        self::BOOL,
        self::DATE,
        self::DATETIME,
        self::JSON,
    ];

    /** @var list<string> */
    public const COMPARABLE = [
        self::INT,
        self::DECIMAL,
        self::DATE,
        self::DATETIME,
    ];

    public static function column(?string $type): ?string
    {
        return match ($type) {
            self::STRING => 'value_string',
            self::INT => 'value_integer',
            self::DECIMAL => 'value_decimal',
            self::BOOL => 'value_boolean',
            self::DATE => 'value_date',
            self::DATETIME => 'value_datetime',
            self::JSON => 'value_json',
            default => null,
        };
    }
}
