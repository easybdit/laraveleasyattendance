<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\DepartmentResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\DepartmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDepartments extends ManageRecords
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
