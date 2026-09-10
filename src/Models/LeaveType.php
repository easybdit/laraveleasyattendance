<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property ?int $days_allowed_per_year
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Leave> $leaves
 */
class LeaveType extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'leave_types';
    }

    protected $fillable = ['name', 'days_allowed_per_year'];

    public function leaves(): HasMany
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
