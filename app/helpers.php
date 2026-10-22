<?php

if (! function_exists('money')) {
    /** Format an amount with the configured currency symbol, e.g. "Rs. 1,250.00". */
    function money(float|string|null $amount, bool $symbol = true): string
    {
        $formatted = number_format((float) $amount, 2);

        return $symbol ? config('distributor.currency').' '.$formatted : $formatted;
    }
}

if (! function_exists('qty')) {
    /** Format a quantity without trailing zeros, e.g. 12 or 2.5. */
    function qty(float|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.');
    }
}
