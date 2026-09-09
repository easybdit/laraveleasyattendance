<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Widgets;

use Easybdit\LaravelEasyAttendance\Models\Attendance;
use Easybdit\LaravelEasyAttendance\Models\AttendanceCorrection;
use Easybdit\LaravelEasyAttendance\Models\AttendanceSummary;
use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\Leave;
use Easybdit\LaravelEasyAttendance\Models\OvertimeRecord;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Top-of-dashboard snapshot: today's attendance breakdown plus a single
 * pending-approvals count across Leave/Overtime/Corrections. Every stat is
 * individually gated behind its `attendance.features.*` flag (same as the
 * resources) and cached for 30s, same reasoning as
 * Filament\Concerns\HasPendingBadge — a dashboard several admins keep
 * open shouldn't mean every one of these count() queries runs on every
 * single page load.
 */
class AttendanceOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return (bool) config('attendance.features.summaries')
            || (bool) config('attendance.features.employees')
            || (bool) config('attendance.features.leave')
            || (bool) config('attendance.features.overtime')
            || (bool) config('attendance.features.corrections', true);
    }

    protected function getStats(): array
    {
        $stats = [];

        if (config('attendance.features.summaries')) {
            $today = static::cached('summary-counts', fn () => AttendanceSummary::query()
                ->whereDate('date', now())
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'));

            $stats[] = Stat::make('Present today', $today->get('present', 0) + $today->get('late', 0))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success');

            $stats[] = Stat::make('Late today', $today->get('late', 0))
                ->icon(Heroicon::OutlinedClock)
                ->color('warning');

            $stats[] = Stat::make('Absent today', $today->get('absent', 0))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger');
        } else {
            $stats[] = Stat::make("Today's punches", static::cached(
                'todays-punches',
                fn () => Attendance::query()->whereDate('time', now())->where('type', 'check_in')->count(),
            ))
                ->icon(Heroicon::OutlinedFingerPrint)
                ->color('gray');
        }

        if (config('attendance.features.employees')) {
            $stats[] = Stat::make('Active employees', static::cached(
                'active-employees',
                fn () => Employee::query()->where('status', 'active')->count(),
            ))
                ->icon(Heroicon::OutlinedUsers)
                ->color('gray');
        }

        $pending = $this->pendingApprovalsCount();

        if ($pending !== null) {
            $stats[] = Stat::make('Pending approvals', $pending)
                ->description('Leave, overtime & correction requests awaiting review')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color($pending > 0 ? 'warning' : 'success');
        }

        return $stats;
    }

    protected function pendingApprovalsCount(): ?int
    {
        $enabled = [
            'leave' => Leave::class,
            'overtime' => OvertimeRecord::class,
            'corrections' => AttendanceCorrection::class,
        ];

        $total = null;

        foreach ($enabled as $feature => $model) {
            $default = $feature === 'corrections';

            if (! config("attendance.features.{$feature}", $default)) {
                continue;
            }

            $total ??= 0;
            $total += static::cached(
                "pending-{$feature}",
                fn () => $model::query()->where('status', 'pending')->count(),
            );
        }

        return $total;
    }

    protected static function cached(string $key, \Closure $callback): mixed
    {
        return Cache::remember("easy-attendance:filament-widget:{$key}", now()->addSeconds(30), $callback);
    }
}
