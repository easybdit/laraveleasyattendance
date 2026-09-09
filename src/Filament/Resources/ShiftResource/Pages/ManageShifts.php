<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\ShiftResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\ShiftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageShifts extends ManageRecords
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
