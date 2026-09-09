<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\SpecialWorkingDayResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\SpecialWorkingDayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSpecialWorkingDays extends ManageRecords
{
    protected static string $resource = SpecialWorkingDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
