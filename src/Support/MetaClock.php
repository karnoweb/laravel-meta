<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Support;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use Karnoweb\Meta\Exceptions\MetaException;

final class MetaClock
{
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    public static function format(CarbonImmutable $instant): string
    {
        return $instant->utc()->format('Y-m-d H:i:s.u');
    }

    public static function parse(DateTimeInterface|string $value): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance(DateTimeImmutable::createFromInterface($value))->utc();
        }

        $value = trim($value);

        if ($value === '') {
            throw new MetaException('A meta instant cannot be empty. Relative expressions such as "+2 weeks" are rejected.');
        }

        $formats = [
            '!Y-m-d H:i:s.u',
            '!Y-m-d H:i:s',
            '!Y-m-d\TH:i:s.uP',
            '!Y-m-d\TH:i:sP',
            '!Y-m-d\TH:i:s.u\Z',
            '!Y-m-d\TH:i:s\Z',
            '!Y-m-d',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $value, 'UTC');
            } catch (\Throwable) {
                continue;
            }

            $errors = CarbonImmutable::getLastErrors();
            $warnings = is_array($errors) ? (int) ($errors['warning_count'] ?? 0) : 0;
            $errorCount = is_array($errors) ? (int) ($errors['error_count'] ?? 0) : 0;

            if ($parsed instanceof CarbonImmutable && $warnings === 0 && $errorCount === 0) {
                return $parsed->utc();
            }
        }

        throw new MetaException("Invalid meta instant [{$value}]. Use an absolute UTC datetime. Relative expressions are rejected.");
    }
}
