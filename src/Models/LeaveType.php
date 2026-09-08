<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = ['name', 'days_allowed_per_year'];

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    /**
     * How much of this leave type an employee has used/has left for a
     * calendar year. A leave counts against the year of its start_date —
     * a request spanning New Year's isn't split across two years.
     *
     * @return array{allowed: ?int, used: int, remaining: ?int}
     */
    public function balanceForEmployee(Employee $employee, ?int $year = null): array
    {
        $year ??= now()->year;

        $used = $this->leaves()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->get()
            ->sum(fn (Leave $leave) => $leave->daysCount());

        return [
            'allowed' => $this->days_allowed_per_year,
            'used' => $used,
            'remaining' => $this->days_allowed_per_year !== null
                ? max(0, $this->days_allowed_per_year - $used)
                : null,
        ];
    }
}
