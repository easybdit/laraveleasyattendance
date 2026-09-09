<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployees extends ManageRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
