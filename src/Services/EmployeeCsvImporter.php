<?php

namespace Easybdit\LaravelEasyAttendance\Services;

use Easybdit\LaravelEasyAttendance\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Plain PHP fgetcsv() — no maatwebsite/excel or PhpSpreadsheet needed to
 * bulk-add employees from a spreadsheet export.
 *
 * Expected header row (order doesn't matter, extra columns are ignored):
 *   employee_code, name, email, phone, designation, device_user_id,
 *   basic_salary, joined_at, status
 * Plus any column prefixed allowance_ (e.g. allowance_house_rent,
 * allowance_medical) — each becomes a key in the employee's allowances
 * map, named without the prefix.
 *
 * One bad row is recorded and skipped rather than failing the whole
 * file — matches this package's existing "one bad item never blocks the
 * rest of the batch" rule (see AttendanceDeviceSyncService, SalaryService).
 */
class EmployeeCsvImporter
{
    /**
     * @return array{imported: int, skipped: int, errors: array<int, array{row: int, message: string}>}
     */
    public function import(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => [['row' => 0, 'message' => 'File is empty or not a valid CSV.']]];
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1; // header was row 1

        while (($line = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }

            $data = $this->rowToAttributes($header, $line);

            $validator = Validator::make($data['attributes'], [
                'employee_code' => ['required', 'string', 'max:50', Rule::unique(Employee::class, 'employee_code')],
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'device_user_id' => ['nullable', 'string', 'max:50', Rule::unique(Employee::class, 'device_user_id')],
                'basic_salary' => ['nullable', 'numeric', 'min:0'],
                'joined_at' => ['nullable', 'date'],
                'status' => ['nullable', 'in:active,inactive'],
            ]);

            if ($validator->fails()) {
                $skipped++;
                $errors[] = ['row' => $rowNumber, 'message' => $validator->errors()->first()];

                continue;
            }

            $attributes = $validator->validated();
            $attributes['status'] = $attributes['status'] ?? 'active';
            $attributes['basic_salary'] = $attributes['basic_salary'] ?? 0;
            if ($data['allowances']) {
                $attributes['allowances'] = $data['allowances'];
            }

            Employee::create($attributes);
            $imported++;
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  string[]  $header
     * @param  array<int, string>  $line
     * @return array{attributes: array<string, mixed>, allowances: array<string, float>}
     */
    private function rowToAttributes(array $header, array $line): array
    {
        $known = ['employee_code', 'name', 'email', 'phone', 'designation', 'device_user_id', 'basic_salary', 'joined_at', 'status'];

        $attributes = [];
        $allowances = [];

        foreach ($header as $i => $column) {
            $value = trim((string) ($line[$i] ?? ''));
            if ($value === '') {
                continue;
            }

            if (in_array($column, $known, true)) {
                $attributes[$column] = $value;
            } elseif (str_starts_with($column, 'allowance_')) {
                $allowances[substr($column, strlen('allowance_'))] = (float) $value;
            }
        }

        return ['attributes' => $attributes, 'allowances' => $allowances];
    }
}
