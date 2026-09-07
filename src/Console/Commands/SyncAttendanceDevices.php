<?php

namespace Easybdit\LaravelEasyAttendance\Console\Commands;

use Easybdit\LaravelEasyAttendance\Models\AttendanceDevice;
use Easybdit\LaravelEasyAttendance\Services\AttendanceDeviceSyncService;
use Illuminate\Console\Command;

/**
 * Schedule this (e.g. every few minutes) so a device's backlog never grows
 * large enough to make an on-demand "Sync now" click time out. Push/ADMS
 * devices (no IP) sync themselves in real time and are skipped here — there
 * is nothing to pull from them.
 *
 *   $schedule->command('attendance:sync-devices')->everyFiveMinutes();
 */
class SyncAttendanceDevices extends Command
{
    protected $signature = 'attendance:sync-devices';

    protected $description = 'Pull attendance logs from every active, IP-configured attendance device';

    public function handle(AttendanceDeviceSyncService $sync): int
    {
        $devices = AttendanceDevice::where('status', 'active')->whereNotNull('ip')->get();

        if ($devices->isEmpty()) {
            $this->components->info('No active pull-mode devices configured.');

            return self::SUCCESS;
        }

        $this->components->info("Syncing {$devices->count()} device(s)...");

        foreach ($devices as $device) {
            try {
                $result = $sync->pull($device);

                if ($result['success']) {
                    $this->line("  [{$device->name}] OK — {$result['message']}");
                } else {
                    $this->components->warn("  [{$device->name}] FAILED — {$result['message']}");
                }
            } catch (\Throwable $e) {
                // One bad device must never stop the rest of the batch.
                $this->components->error("  [{$device->name}] EXCEPTION — {$e->getMessage()}");
                report($e);
            }
        }

        return self::SUCCESS;
    }
}
