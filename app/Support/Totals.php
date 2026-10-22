<?php

namespace App\Support;

/**
 * Line and document totals for quotations, sales orders and invoices.
 *
 * Each line can carry its own tax % (from the product). A bill-level discount is spread over the lines in
 * proportion to their value before tax, so tax is charged on what the dealer actually pays.
 * $taxPercent is the fallback rate for lines without their own tax_percent (older callers / documents).
 */
class Totals
{
    /**
     * @param  array<int, array{product_id:int, quantity:float, unit_price:float, discount_percent?:float, tax_percent?:float}>  $lines
     * @return array{lines: array, subtotal: float, discount: float, tax: float, total: float}
     */
    public static function calculate(array $lines, float $discount = 0, float $taxPercent = 0): array
    {
        $subtotal = 0;

        foreach ($lines as $i => $line) {
            $gross = (float) $line['quantity'] * (float) $line['unit_price'];
            $lineTotal = round($gross * (1 - (float) ($line['discount_percent'] ?? 0) / 100), 2);
            $lines[$i]['discount_percent'] = (float) ($line['discount_percent'] ?? 0);
            $lines[$i]['tax_percent'] = (float) (array_key_exists('tax_percent', $line) && $line['tax_percent'] !== null && $line['tax_percent'] !== '' ? $line['tax_percent'] : $taxPercent);
            $lines[$i]['line_total'] = $lineTotal;
            $subtotal += $lineTotal;
        }

        $discount = min(max(0, $discount), $subtotal);
        $ratio = $subtotal > 0 ? ($subtotal - $discount) / $subtotal : 1;

        $tax = 0;
        foreach ($lines as $i => $line) {
            $lines[$i]['tax_amount'] = round($line['line_total'] * $ratio * $line['tax_percent'] / 100, 2);
            $tax += $lines[$i]['tax_amount'];
        }

        return [
            'lines' => $lines,
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'tax' => round($tax, 2),
            'total' => round($subtotal - $discount + $tax, 2),
        ];
    }
}
