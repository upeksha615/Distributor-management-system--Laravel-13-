<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options as PdfOptions;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads a table of data as Excel (xlsx), CSV or PDF.
 *
 * Columns: ['Heading' => 'text'|'number'|'money'|'qty'] in the same order as each row's values.
 */
class Exporter
{
    public const FORMATS = ['xlsx', 'csv', 'pdf'];

    /**
     * @param  array<string, string>  $columns
     * @param  iterable<array>  $rows  plain value arrays, in column order
     * @param  array|null  $totals  optional footer row (same length as columns, nulls for blanks)
     */
    public static function download(
        string $format,
        string $name,
        string $title,
        array $columns,
        iterable $rows,
        ?string $subtitle = null,
        ?array $totals = null,
    ): Response {
        $filename = str($name)->slug().'-'.now()->format('Ymd-His').'.'.$format;
        $rows = collect($rows)->map(fn ($r) => array_values($r))->all();

        return match ($format) {
            'xlsx' => self::spreadsheet(new XlsxWriter(self::xlsxOptions($columns, $rows)), $filename, $columns, $rows, $totals),
            'csv' => self::spreadsheet(new CsvWriter(), $filename, $columns, $rows, $totals),
            'pdf' => self::pdf($filename, $title, $subtitle, $columns, $rows, $totals),
            default => abort(404),
        };
    }

    /** Render a Blade view to a PDF download (used for invoices, POs, statements...). */
    public static function pdfView(string $view, array $data, string $filename, string $orientation = 'portrait'): Response
    {
        $html = view($view, $data)->render();

        return response(self::renderPdf($html, $orientation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
        ]);
    }

    private static function spreadsheet($writer, string $filename, array $columns, array $rows, ?array $totals): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'exp');
        $types = array_values($columns);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValuesWithStyle(array_keys($columns), (new Style())->withFontBold(true)->withBackgroundColor('E2E8F0')));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(self::typed($row, $types)));
        }
        if ($totals) {
            $writer->addRow(Row::fromValuesWithStyle(self::typed($totals, $types), (new Style())->withFontBold(true)));
        }
        $writer->close();

        return response()->download($path, $filename)->deleteFileAfterSend();
    }

    private static function xlsxOptions(array $columns, array $rows): XlsxOptions
    {
        $options = new XlsxOptions();
        foreach (array_keys($columns) as $i => $heading) {
            $longest = max(mb_strlen($heading), ...array_map(fn ($r) => mb_strlen((string) ($r[$i] ?? '')), array_slice($rows, 0, 500) ?: [[]]));
            $options->setColumnWidth(min(60, max(8, $longest + 2)), $i + 1);
        }

        return $options;
    }

    /** Numbers stay numeric in spreadsheets so they can be summed. */
    private static function typed(array $row, array $types): array
    {
        foreach ($row as $i => $value) {
            if ($value !== null && $value !== '' && in_array($types[$i] ?? 'text', ['number', 'money', 'qty']) && is_numeric($value)) {
                $row[$i] = $types[$i] === 'number' ? (int) $value : round((float) $value, 2);
            } else {
                $row[$i] = $value === null ? '' : (string) $value;
            }
        }

        return $row;
    }

    private static function pdf(string $filename, string $title, ?string $subtitle, array $columns, array $rows, ?array $totals): Response
    {
        $html = view('exports.table-pdf', [
            'title' => $title,
            'subtitle' => $subtitle,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
        ])->render();

        return response(self::renderPdf($html, count($columns) > 6 ? 'landscape' : 'portrait'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private static function renderPdf(string $html, string $orientation): string
    {
        $options = new PdfOptions();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', $orientation);
        $pdf->render();

        // Page numbers in the footer.
        $canvas = $pdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 90, $canvas->get_height() - 24, 'Page {PAGE_NUM} of {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.4, 0.4, 0.4]);

        return $pdf->output();
    }
}
