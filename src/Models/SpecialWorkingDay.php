<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Easybdit\LaravelEasyAttendance\Support\ShiftResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Marks a date an employee was specifically asked to work despite it
 * normally being off (their shift's off_day, or a company Holiday) — so
 * SalaryService can pay extra for it instead of that day just quietly
 * counting as an ordinary "present".
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $date
 * @property string $type
 * @property bool $is_payable
 * @property ?string $payment_amount
 * @property ?string $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee $employee
 */
class SpecialWorkingDay extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'special_working_days';
    }

    protected $fillable = ['employee_id', 'date', 'type', 'is_payable', 'payment_amount', 'note'];

    protected $casts = [
        'date' => 'date',
        'is_payable' => 'boolean',
        'payment_amount' => 'decimal:2',
    ];

    /**
     * type is derived from the date itself, not chosen by hand — keeps it
     * from drifting out of sync with the actual shift/holiday it describes.
     */
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $model->type = static::detectType((int) $model->employee_id, (string) $model->date);
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public static function detectType(int $employeeId, string $date): string
    {
        if (Holiday::onDate($date)) {
            return 'holiday';
        }

        $employee = Employee::find($employeeId);
        if ($employee && (new ShiftResolver)->resolve($employee, $date)['is_off_day']) {
            return 'day_off';
        }

        return 'other';
    }

    public function scopePayable($query)
    {
        return $query->where('is_payable', true);
    }

    /**
     * Priority: a custom amount on the record itself → config for the
     * type (fixed_amount | multiplier | daily_rate) → the plain daily
     * rate as a last resort (always the fallback for type 'other', which
     * has no dedicated config section — it's inherently ad hoc).
     */
    public function getPaymentAmount(float $dailyRate): float
    {
        if ($this->payment_amount && $this->payment_amount > 0) {
            return (float) $this->payment_amount;
        }

        if ($this->type === 'other') {
            return $dailyRate;
        }

        $prefix = $this->type === 'day_off' ? 'day_off' : 'holiday';
        $paymentType = config("attendance.special_working_days.{$prefix}_payment_type", 'daily_rate');

        return match ($paymentType) {
            'fixed_amount' => (float) config("attendance.special_working_days.{$prefix}_fixed_amount", 1000),
            'multiplier' => $dailyRate * (float) config("attendance.special_working_days.{$prefix}_multiplier", 1.0),
            default => $dailyRate,
        };
    }
}
