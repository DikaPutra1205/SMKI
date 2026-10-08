<?php

namespace App\Support;

/**
 * Neutralize spreadsheet formula injection (CSV/XLSX exports).
 *
 * Cells starting with = + - @ (or tab/CR) execute as formulas when the
 * export is opened in Excel. Prefixing a single quote forces text.
 */
final class SpreadsheetSecurity
{
    public static function neutralize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        // Look past leading whitespace: a formula after spaces/tabs still
        // evaluates in some spreadsheet hosts, so prefix conservatively.
        $first = ltrim($value, " \t\r\n")[0] ?? '';

        return in_array($first, ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
