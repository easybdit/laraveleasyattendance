<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\LeaveTypeResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\LeaveTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLeaveTypes extends ManageRecords
{
    protected static string $resource = LeaveTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
