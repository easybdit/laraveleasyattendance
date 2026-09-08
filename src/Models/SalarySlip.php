<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Model;

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

    public function employee()
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
