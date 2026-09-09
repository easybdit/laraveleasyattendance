<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\LeaveResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\LeaveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLeaves extends ManageRecords
{
    protected static string $resource = LeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
