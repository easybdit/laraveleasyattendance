<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Carbon\Carbon;
use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Attendance extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'attendances';
    }

    protected $fillable = [
        'subject_type', 'subject_id',
        'time', 'type', 'source', 'is_manual', 'meta',
        'device_id', 'device_user_id',
    ];

    protected $casts = [
        'time' => 'datetime',
        'is_manual' => 'boolean',
        'meta' => 'array',
    ];

    public function subject()
    {
        return $this->morphTo();
    }

    public function device()
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }

    /**
     * Resolve a day's effective first-in/last-out from a collection of
     * punches for a single subject/date, giving manual entries priority
     * over device/api punches within each type — so a correction always
     * wins over a stray device punch instead of just sitting beside it.
     *
     * @param  Collection<int, Attendance>  $punches
     * @return array{first_in: ?Carbon, last_out: ?Carbon}
     */
    public static function resolveDayWindow(Collection $punches): array
    {
        $checkIns = $punches->where('type', 'check_in');
        $checkOuts = $punches->where('type', 'check_out');

        $manualIn = $checkIns->where('is_manual', true);
        $manualOut = $checkOuts->where('is_manual', true);

        $firstIn = $manualIn->isNotEmpty()
            ? $manualIn->min('time')
            : ($checkIns->isNotEmpty() ? $checkIns->min('time') : $punches->min('time'));

        $lastOut = $manualOut->isNotEmpty()
            ? $manualOut->max('time')
            : ($checkOuts->isNotEmpty() ? $checkOuts->max('time') : $punches->max('time'));

        return ['first_in' => $firstIn, 'last_out' => $lastOut];
    }
}
