<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceCorrectionResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceCorrectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAttendanceCorrections extends ManageRecords
{
    protected static string $resource = AttendanceCorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
