<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row of an employee's roster/schedule — "this employee works this
 * shift from this date [to this date, or still ongoing]". Multiple rows
 * let an employee's schedule change over time without losing history.
 */
class EmployeeShift extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'employee_shifts';
    }

    protected $fillable = ['employee_id', 'shift_id', 'start_date', 'end_date'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function scopeCovering($query, string $date)
    {
        // whereDate(), not where() — see Holiday::onDate()'s comment for
        // why an exact-string comparison against a `date`-cast column is
        // MySQL-only. whereDate() is safe on any driver.
        return $query->whereDate('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
            });
    }
}
