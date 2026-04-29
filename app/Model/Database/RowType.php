<?php

declare(strict_types=1);

namespace App\Model\Database;

final class RowType
{
    public static function string(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Expected string, got %s', get_debug_type($value)));
        }

        return $value;
    }

    public static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Expected string|null, got %s', get_debug_type($value)));
        }

        return $value;
    }

    public static function int(mixed $value): int
    {
        if (!is_int($value)) {
            throw new \UnexpectedValueException(sprintf('Expected int, got %s', get_debug_type($value)));
        }

        return $value;
    }

    public static function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!is_int($value)) {
            throw new \UnexpectedValueException(sprintf('Expected int|null, got %s', get_debug_type($value)));
        }

        return $value;
    }

    public static function float(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        throw new \UnexpectedValueException(sprintf('Expected float, got %s', get_debug_type($value)));
    }

    /** Converts int|string|null form values to int (HTML inputs always send strings). */
    public static function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new \UnexpectedValueException(sprintf('Expected int-compatible value, got %s', get_debug_type($value)));
    }
}
