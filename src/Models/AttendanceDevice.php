<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property ?string $ip
 * @property ?int $port
 * @property ?string $comm_key
 * @property ?string $serial_number
 * @property ?string $model
 * @property string $status
 * @property ?Carbon $last_synced_at
 * @property ?Carbon $last_seen_at
 * @property ?int $sync_fail_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read bool $is_online
 * @property-read string $connection_mode
 * @property-read Collection<int, Attendance> $attendances
 */
class AttendanceDevice extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'attendance_devices';
    }

    protected $fillable = [
        'name', 'ip', 'port', 'comm_key', 'serial_number',
        'model', 'status', 'last_synced_at', 'last_seen_at', 'sync_fail_count',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    protected $appends = ['is_online', 'connection_mode'];

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'device_id');
    }

    public function getIsOnlineAttribute(): bool
    {
        $threshold = config('attendance.device_sync.online_threshold_seconds', 90);

        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds($threshold));
    }

    /**
     * 'pull' (has an IP, we connect out to it), 'push' (has a serial number,
     * it dials home to us), or 'unconfigured' (neither set yet).
     */
    public function getConnectionModeAttribute(): string
    {
        if ($this->ip) {
            return 'pull';
        }

        return $this->serial_number ? 'push' : 'unconfigured';
    }
}
