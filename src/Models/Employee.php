<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Events\LeaveRequested;
use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Easybdit\LaravelEasyAttendance\Traits\HasAttendance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The package's own ready-made "subject" — for anyone who wants a real
 * employee record (salary, allowances, designation) instead of pointing
 * attendance.subject_model at their own User/Employee class. Optional:
 * only migrated when features.employees is on. To actually use it as the
 * attendance subject, set ATTENDANCE_SUBJECT_MODEL to this class.
 */
class Employee extends Model
{
    use HasAttendance, HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'employees';
    }

    protected $fillable = [
        'employee_code', 'name', 'email', 'phone', 'designation',
        'department_id', 'designation_id',
        'device_user_id', 'basic_salary', 'allowances', 'joined_at', 'status',
    ];

    protected $casts = [
        'allowances' => 'array',
        'basic_salary' => 'decimal:2',
        'joined_at' => 'date',
    ];

    public function shiftAssignments()
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designationRecord()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function salarySlips()
    {
        return $this->hasMany(SalarySlip::class);
    }

    public function summaries()
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function overtimeRecords()
    {
        return $this->hasMany(OvertimeRecord::class);
    }

    public function specialWorkingDays()
    {
        return $this->hasMany(SpecialWorkingDay::class);
    }

    public function totalAllowances(): float
    {
        return array_sum($this->allowances ?? []);
    }

    public function requestLeave(array $attributes): Leave
    {
        $leave = $this->leaves()->create(array_merge(['status' => 'pending'], $attributes));

        event(new LeaveRequested($leave));

        return $leave;
    }

    /**
     * Allowed/used/remaining for every leave type, for one calendar year.
     *
     * @return Collection<int, array{leave_type_id: int, leave_type: string, allowed: ?int, used: int, remaining: ?int}>
     */
    public function leaveBalances(?int $year = null): Collection
    {
        return LeaveType::all()->map(fn (LeaveType $type) => array_merge(
            ['leave_type_id' => $type->id, 'leave_type' => $type->name],
            $type->balanceForEmployee($this, $year)
        ));
    }
}
