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
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
