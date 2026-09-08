<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'shifts';
    }

    protected $fillable = ['name', 'start_time', 'end_time', 'late_grace_minutes', 'off_days'];

    protected $casts = [
        'off_days' => 'array',
    ];

    public function assignments()
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function isOffDay(\DateTimeInterface $date): bool
    {
        return in_array($date->format('l'), $this->off_days ?? [], true);
    }
}
