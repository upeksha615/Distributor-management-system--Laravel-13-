<?php

namespace App\Support;

/** Formats a value for display in PDF tables. */
class ExportFormat
{
    public static function cell(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'money' => is_numeric($value) ? number_format((float) $value, 2) : (string) $value,
            'qty' => is_numeric($value) ? qty($value) : (string) $value,
            'number' => is_numeric($value) ? number_format((float) $value) : (string) $value,
            default => (string) $value,
        };
    }
}
