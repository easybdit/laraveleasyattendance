<?php

namespace Easybdit\LaravelEasyAttendance\Filament;

use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceCorrectionResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceDeviceResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceSummaryResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\DepartmentResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\DesignationResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\HolidayResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\LeaveResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\LeaveTypeResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\OvertimeRecordResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\SalarySlipResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\ShiftResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\SpecialWorkingDayResource;
use Easybdit\LaravelEasyAttendance\Filament\Widgets\AttendanceOverviewWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Optional Filament v5 admin panel integration — a full click-to-manage UI
 * over every module this package ships (check-in/out, corrections, device
 * sync, employees, shifts, leave, holidays, overtime, special working days,
 * salary slips), built entirely on top of the package's own public API
 * (models, config, the approve()/reject() methods on Leave/OvertimeRecord/
 * AttendanceCorrection) — nothing here reaches around it.
 *
 * Not autoloaded into your panel automatically: filament/filament is a
 * suggested, not required, dependency (this package works standalone with
 * zero UI), so register it yourself once you have Filament installed:
 *
 *   composer require filament/filament
 *
 *   // app/Providers/Filament/AdminPanelProvider.php
 *   use Easybdit\LaravelEasyAttendance\Filament\EasyAttendancePlugin;
 *
 *   public function panel(Panel $panel): Panel
 *   {
 *       return $panel
 *           // ...
 *           ->plugin(EasyAttendancePlugin::make());
 *   }
 *
 * Every resource is individually gated by the same `attendance.features.*`
 * flag its table/routes already check (see Concerns\RequiresFeature) — so
 * a minimal check-in/out-only install just shows the Attendance resource,
 * and turning on ATTENDANCE_FEATURE_HR_CORE lights up the rest without any
 * extra config on the Filament side. Also ships a dashboard stats widget
 * (Widgets\AttendanceOverviewWidget) and navigation badges on Leave/
 * Overtime/Corrections (Concerns\HasPendingBadge) — both feature-gated
 * and query-cached the same way, so they stay cheap on a busy panel.
 */
class EasyAttendancePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'easy-attendance';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources($this->resources())
            ->widgets([AttendanceOverviewWidget::class]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * @return array<class-string>
     */
    protected function resources(): array
    {
        return [
            AttendanceResource::class,
            AttendanceCorrectionResource::class,
            AttendanceDeviceResource::class,
            AttendanceSummaryResource::class,
            EmployeeResource::class,
            DepartmentResource::class,
            DesignationResource::class,
            ShiftResource::class,
            HolidayResource::class,
            LeaveTypeResource::class,
            LeaveResource::class,
            OvertimeRecordResource::class,
            SpecialWorkingDayResource::class,
            SalarySlipResource::class,
        ];
    }
}
