<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Support;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Karnoweb\Meta\Exceptions\MetaTypeException;

final class MetaValue
{
    /** @var list<string> */
    public const COLUMNS = [
        'value_string',
        'value_string_hash',
        'value_integer',
        'value_decimal',
        'value_decimal_text',
        'value_decimal_sort',
        'value_boolean',
        'value_date',
        'value_datetime',
        'value_json',
    ];

    /**
     * @return array{value_type: string, columns: array<string, mixed>}
     */
    public static function encode(mixed $value, ?string $type, int $maxString, int $maxJson): array
    {
        $resolved = self::resolveType($value, $type);

        $columns = [];

        foreach (self::COLUMNS as $column) {
            $columns[$column] = null;
        }

        if ($resolved === MetaType::STRING) {
            $string = self::stringValue($value, $maxString);
            $columns['value_string'] = $string;
            $columns['value_string_hash'] = hash('sha256', $string);
        } elseif ($resolved === MetaType::DECIMAL) {
            $canonical = self::normalizeDecimal(self::decimalSource($value));
            $columns['value_decimal'] = $canonical;
            $columns['value_decimal_text'] = $canonical;
            $columns['value_decimal_sort'] = self::decimalSortKey($canonical);
        } else {
            $stored = match ($resolved) {
                MetaType::NULL => null,
                MetaType::INT => self::intValue($value),
                MetaType::BOOL => self::boolValue($value),
                MetaType::DATE => self::dateValue($value),
                MetaType::DATETIME => MetaClock::format(self::dateTimeValue($value)),
                MetaType::JSON => self::jsonValue($value, $maxJson),
                default => throw new MetaTypeException("Unsupported meta type [{$resolved}]."),
            };

            $column = MetaType::column($resolved);

            if ($column !== null) {
                $columns[$column] = $stored;
            }
        }

        return [
            'value_type' => $resolved,
            'columns' => $columns,
        ];
    }

    /**
     * @param  array{value_type: string, columns: array<string, mixed>}  $encoded
     */
    public static function apply(Model $record, array $encoded): void
    {
        foreach (self::COLUMNS as $column) {
            $record->setAttribute($column, null);
        }

        $record->setAttribute('value_type', $encoded['value_type']);

        foreach ($encoded['columns'] as $column => $stored) {
            $record->setAttribute($column, $stored);
        }
    }

    public static function clear(Model $record): void
    {
        $record->setAttribute('value_type', null);

        foreach (self::COLUMNS as $column) {
            $record->setAttribute($column, null);
        }
    }

    public static function decode(Model $record): mixed
    {
        $attributes = $record->getAttributes();
        $type = $attributes['value_type'] ?? null;

        if ($type === null || $type === MetaType::NULL) {
            return null;
        }

        $column = MetaType::column((string) $type);
        $raw = $column === null ? null : ($attributes[$column] ?? null);

        return match ($type) {
            MetaType::STRING => (string) $raw,
            MetaType::INT => (int) $raw,
            MetaType::DECIMAL => self::readDecimal($attributes),
            MetaType::BOOL => self::readBool($raw),
            MetaType::DATE => self::readDate($raw),
            MetaType::DATETIME => self::readDateTime($raw),
            MetaType::JSON => self::readJson($raw),
            default => throw new MetaTypeException("Stored meta type [{$type}] is not supported."),
        };
    }

    /**
     * @return array{type: string, column: string, stored: mixed}
     */
    public static function binding(mixed $value, ?string $type, int $maxString, int $maxJson): array
    {
        $encoded = self::encode($value, $type, $maxString, $maxJson);

        if ($encoded['value_type'] === MetaType::NULL) {
            throw new MetaTypeException('Null meta values cannot be compared with whereMeta. Use whereMetaNull().');
        }

        if ($encoded['value_type'] === MetaType::JSON) {
            throw new MetaTypeException('JSON meta values cannot be compared with whereMeta. Use whereMetaJsonContains().');
        }

        $type = $encoded['value_type'];

        if ($type === MetaType::STRING) {
            return [
                'type' => $type,
                'column' => 'value_string_hash',
                'stored' => $encoded['columns']['value_string_hash'],
                'compare' => $encoded['columns']['value_string'],
                'guard' => [
                    'column' => 'value_string',
                    'stored' => $encoded['columns']['value_string'],
                ],
            ];
        }

        if ($type === MetaType::DECIMAL) {
            return [
                'type' => $type,
                'column' => 'value_decimal_sort',
                'stored' => $encoded['columns']['value_decimal_sort'],
                'compare' => $encoded['columns']['value_decimal_text'],
                'guard' => null,
            ];
        }

        $column = MetaType::column($type);

        return [
            'type' => $type,
            'column' => (string) $column,
            'stored' => $encoded['columns'][$column],
            'compare' => $encoded['columns'][$column],
            'guard' => null,
        ];
    }

    public static function compare(string $type, mixed $left, mixed $right): int
    {
        return match ($type) {
            MetaType::INT => ((int) $left) <=> ((int) $right),
            MetaType::DECIMAL => self::compareDecimal((string) $left, (string) $right),
            MetaType::DATE, MetaType::DATETIME => strcmp((string) $left, (string) $right),
            default => throw new MetaTypeException("Type [{$type}] is not ordered."),
        };
    }

    private static function resolveType(mixed $value, ?string $type): string
    {
        if ($type !== null && ! in_array($type, MetaType::ALL, true)) {
            throw new MetaTypeException("Unknown meta type [{$type}].");
        }

        if ($value === null) {
            if ($type !== null && $type !== MetaType::NULL) {
                throw new MetaTypeException('Null can only be stored as the null meta type.');
            }

            return MetaType::NULL;
        }

        if ($type === MetaType::NULL) {
            throw new MetaTypeException('The null meta type only accepts a null value.');
        }

        if (is_float($value)) {
            throw new MetaTypeException('Decimal meta rejects PHP floats. Pass a decimal string so precision is not lost.');
        }

        if ($type !== null) {
            self::assertMatchesDeclaredType($value, $type);

            return $type;
        }

        return match (true) {
            is_string($value) => MetaType::STRING,
            is_int($value) => MetaType::INT,
            is_bool($value) => MetaType::BOOL,
            $value instanceof DateTimeInterface => MetaType::DATETIME,
            is_array($value) => MetaType::JSON,
            default => throw new MetaTypeException('This PHP value cannot be stored as meta. Objects, resources, and enums are rejected.'),
        };
    }

    private static function assertMatchesDeclaredType(mixed $value, string $type): void
    {
        $ok = match ($type) {
            MetaType::STRING => is_string($value),
            MetaType::INT => is_int($value),
            MetaType::DECIMAL => is_string($value) || is_int($value),
            MetaType::BOOL => is_bool($value),
            MetaType::DATE => is_string($value),
            MetaType::DATETIME => $value instanceof DateTimeInterface || is_string($value),
            MetaType::JSON => is_array($value),
            default => false,
        };

        if (! $ok) {
            $given = get_debug_type($value);

            throw new MetaTypeException("Value of type [{$given}] does not match declared meta type [{$type}].");
        }
    }

    private static function stringValue(mixed $value, int $maxString): string
    {
        if (! is_string($value)) {
            throw new MetaTypeException('String meta requires a PHP string. Numeric strings are not cast.');
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new MetaTypeException('String meta must be valid UTF-8.');
        }

        if (mb_strlen($value) > $maxString) {
            throw new MetaTypeException("String meta exceeds {$maxString} characters.");
        }

        return $value;
    }

    private static function intValue(mixed $value): int
    {
        if (! is_int($value)) {
            throw new MetaTypeException('Integer meta requires a PHP int. Numeric strings are not cast.');
        }

        return $value;
    }

    private static function decimalSource(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            throw new MetaTypeException('Decimal meta rejects PHP floats. Pass a decimal string so precision is not lost.');
        }

        if (! is_string($value)) {
            throw new MetaTypeException('Decimal meta requires a decimal string or int.');
        }

        return trim($value);
    }

    public static function normalizeDecimal(string $value): string
    {
        $value = trim($value);

        if (! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $value)) {
            throw new MetaTypeException("Invalid decimal [{$value}]. Use a base-10 number with at most 20 integer digits and 10 fractional digits.");
        }

        $negative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '+-');
        [$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $integer = ltrim($integer, '0');

        if ($integer === '') {
            $integer = '0';
        }

        if (strlen($fraction) > 10 || strlen($integer) > 20) {
            throw new MetaTypeException('Decimal is outside decimal(30, 10).');
        }

        $fraction = str_pad($fraction, 10, '0');
        $zero = $integer === '0' && $fraction === '0000000000';

        return ($negative && ! $zero ? '-' : '').$integer.'.'.$fraction;
    }

    public static function decimalSortKey(string $canonical): string
    {
        $negative = str_starts_with($canonical, '-');
        [$integer, $fraction] = explode('.', ltrim($canonical, '-'));
        $digits = str_pad($integer, 20, '0', STR_PAD_LEFT).$fraction;

        if ($negative) {
            $digits = implode('', array_map(
                static fn (string $digit): string => (string) (9 - (int) $digit),
                str_split($digits),
            ));

            return '0'.$digits;
        }

        return '1'.$digits;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function readDecimal(array $attributes): string
    {
        $text = $attributes['value_decimal_text'] ?? null;

        if (! is_string($text) || $text === '') {
            throw new MetaTypeException('Stored decimal has no canonical text. Precision that was already lost cannot be recovered.');
        }

        return self::normalizeDecimal($text);
    }

    private static function compareDecimal(string $left, string $right): int
    {
        $leftNegative = str_starts_with($left, '-');
        $rightNegative = str_starts_with($right, '-');

        if ($leftNegative !== $rightNegative) {
            return $leftNegative ? -1 : 1;
        }

        $leftBody = ltrim($left, '-');
        $rightBody = ltrim($right, '-');
        [$leftInteger, $leftFraction] = explode('.', $leftBody);
        [$rightInteger, $rightFraction] = explode('.', $rightBody);

        $comparison = strlen($leftInteger) <=> strlen($rightInteger);

        if ($comparison === 0) {
            $comparison = strcmp($leftInteger, $rightInteger);
        }

        if ($comparison === 0) {
            $comparison = strcmp($leftFraction, $rightFraction);
        }

        if ($comparison === 0) {
            return 0;
        }

        $sign = $comparison < 0 ? -1 : 1;

        return $leftNegative ? -$sign : $sign;
    }

    private static function boolValue(mixed $value): int
    {
        if (! is_bool($value)) {
            throw new MetaTypeException('Boolean meta requires a PHP bool. 0, 1, and "false" are not booleans.');
        }

        return $value ? 1 : 0;
    }

    private static function readBool(mixed $raw): bool
    {
        return (int) $raw === 1;
    }

    private static function dateValue(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new MetaTypeException('Date meta requires a Y-m-d string.');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        $warnings = is_array($errors) ? (int) ($errors['warning_count'] ?? 0) : 0;
        $errorCount = is_array($errors) ? (int) ($errors['error_count'] ?? 0) : 0;

        if (! $parsed instanceof DateTimeImmutable || $warnings > 0 || $errorCount > 0 || $parsed->format('Y-m-d') !== $value) {
            throw new MetaTypeException("Invalid date [{$value}].");
        }

        return $value;
    }

    private static function readDate(mixed $raw): CarbonImmutable
    {
        $date = substr((string) $raw, 0, 10);

        return CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');
    }

    private static function dateTimeValue(mixed $value): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface || is_string($value)) {
            return MetaClock::parse($value);
        }

        throw new MetaTypeException('Datetime meta requires a DateTimeInterface or an absolute datetime string.');
    }

    private static function readDateTime(mixed $raw): CarbonImmutable
    {
        return MetaClock::parse((string) $raw);
    }

    private static function jsonValue(mixed $value, int $maxJson): string
    {
        if (! is_array($value)) {
            throw new MetaTypeException('JSON meta requires a PHP array. Objects are rejected.');
        }

        self::assertJsonNode($value);

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (strlen($encoded) > $maxJson) {
            throw new MetaTypeException("JSON meta exceeds {$maxJson} bytes.");
        }

        return $encoded;
    }

    private static function assertJsonNode(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                self::assertJsonNode($item);
            }

            return;
        }

        if ($value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            if (is_float($value) && ! is_finite($value)) {
                throw new MetaTypeException('JSON meta rejects non-finite numbers.');
            }

            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                throw new MetaTypeException('JSON meta strings must be valid UTF-8.');
            }

            return;
        }

        throw new MetaTypeException('JSON meta rejects PHP objects, resources, and enums.');
    }

    private static function readJson(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new MetaTypeException('Stored JSON meta is not an array.');
        }

        return $decoded;
    }
}
