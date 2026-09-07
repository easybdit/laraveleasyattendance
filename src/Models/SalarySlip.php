<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Illuminate\Database\Eloquent\Model;

class SalarySlip extends Model
{
    protected $fillable = [
        'employee_id', 'year', 'month', 'basic_salary', 'allowances',
        'present_days', 'absent_days', 'late_days', 'leave_days',
        'deduction_amount', 'net_salary', 'generated_at',
    ];

    protected $casts = [
        'allowances' => 'array',
        'basic_salary' => 'decimal:2',
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
