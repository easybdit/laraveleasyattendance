<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of an employee's roster/schedule — "this employee works this
 * shift from this date [to this date, or still ongoing]". Multiple rows
 * let an employee's schedule change over time without losing history.
 */
class EmployeeShift extends Model
{
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
        return $query->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            });
    }
}
