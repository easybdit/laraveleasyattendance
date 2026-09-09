<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\DesignationResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\DesignationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDesignations extends ManageRecords
{
    protected static string $resource = DesignationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
