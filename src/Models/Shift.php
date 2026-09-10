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
 * @property string $start_time
 * @property string $end_time
 * @property ?int $late_grace_minutes
 * @property ?array<int, string> $off_days
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, EmployeeShift> $assignments
 */
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

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function isOffDay(\DateTimeInterface $date): bool
    {
        return in_array($date->format('l'), $this->off_days ?? [], true);
    }
}
