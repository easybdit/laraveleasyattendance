<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAttendances extends ManageRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
