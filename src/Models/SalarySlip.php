<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $year
 * @property int $month
 * @property string $basic_salary
 * @property ?array<string, float|int> $allowances
 * @property ?int $present_days
 * @property ?int $absent_days
 * @property ?int $late_days
 * @property ?int $leave_days
 * @property string $overtime_hours
 * @property string $overtime_amount
 * @property string $special_pay_amount
 * @property string $deduction_amount
 * @property string $net_salary
 * @property ?Carbon $generated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read float $gross_salary
 * @property-read Employee $employee
 */
class SalarySlip extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'salary_slips';
    }

    protected $fillable = [
        'employee_id', 'year', 'month', 'basic_salary', 'allowances',
        'present_days', 'absent_days', 'late_days', 'leave_days',
        'overtime_hours', 'overtime_amount', 'special_pay_amount',
        'deduction_amount', 'net_salary', 'generated_at',
    ];

    protected $casts = [
        'allowances' => 'array',
        'basic_salary' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'special_pay_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public function getGrossSalaryAttribute(): float
    {
        return round((float) $this->basic_salary + array_sum($this->allowances ?? []), 2);
    }
}
