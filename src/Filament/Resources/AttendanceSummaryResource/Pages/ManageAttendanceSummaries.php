<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceSummaryResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceSummaryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAttendanceSummaries extends ManageRecords
{
    protected static string $resource = AttendanceSummaryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
