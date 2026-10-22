<?php

namespace App\Support;

use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

class Barcode
{
    /** Inline SVG Code 128 barcode (readable by any USB scanner). */
    public static function svg(string $code, float $width = 200, float $height = 40): string
    {
        $barcode = (new TypeCode128())->getBarcode($code);

        return (new SvgRenderer())->render($barcode, $width, $height);
    }
}
