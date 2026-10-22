<?php

namespace App\Support;

use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/** Reads the first sheet of an xlsx or csv file into rows keyed by normalised header names. */
class SpreadsheetReader
{
    /**
     * @return array<int, array<string, string>> keyed by spreadsheet row number (header = row 1)
     */
    public static function rows(string $path, string $extension): array
    {
        $reader = strtolower($extension) === 'csv' ? new CsvReader() : new XlsxReader();
        $reader->open($path);

        $headers = null;
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $line = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $line++;
                $values = array_map(fn ($v) => self::clean($v), $row->toArray());

                if ($headers === null) {
                    $headers = array_map(fn ($h) => self::key($h), $values);
                    continue;
                }
                if (implode('', $values) === '') {
                    continue; // blank line
                }

                $rows[$line] = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), ''));
            }
            break; // first sheet only
        }

        $reader->close();

        return $rows;
    }

    /** "Selling Price (Rs.)" -> "selling_price" */
    public static function key(string $heading): string
    {
        $heading = preg_replace('/\(.*?\)/', '', $heading);

        return (string) str($heading)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');
    }

    private static function clean(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        // Strip a UTF-8 byte order mark and surrounding spaces.
        return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value));
    }
}
