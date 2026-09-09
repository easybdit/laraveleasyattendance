<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceDeviceResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceDeviceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAttendanceDevices extends ManageRecords
{
    protected static string $resource = AttendanceDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
