<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plain PHP fputcsv() streamed to the response — no maatwebsite/excel or
 * PhpSpreadsheet needed for a report someone just wants to open in Excel
 * or Sheets. Every report controller method takes an optional
 * ?format=csv; JSON stays the default.
 */
trait ExportsCsv
{
    protected function wantsCsv(\Illuminate\Http\Request $request): bool
    {
        return $request->query('format') === 'csv';
    }

    /**
     * @param  string[]  $header
     * @param  iterable<int, array<int, scalar|null>>  $rows  Each row already in header order.
     */
    protected function csvResponse(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);

            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'escapeCsvFormula'], $row));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Formula-injection guard: a cell whose value happens to start with
     * =, +, -, or @ (a name/note field, say) opens as a live formula the
     * moment someone opens this export in Excel/Sheets instead of as
     * plain text — a well-known CSV-export risk, not specific to this
     * package. A leading apostrophe forces spreadsheet software to treat
     * it as text; invisible in the cell, harmless for anything downstream
     * that just reads the CSV back as data.
     */
    protected function escapeCsvFormula(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }
}
