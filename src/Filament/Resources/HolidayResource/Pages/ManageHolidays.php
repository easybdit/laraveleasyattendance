<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\HolidayResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\HolidayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHolidays extends ManageRecords
{
    protected static string $resource = HolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
