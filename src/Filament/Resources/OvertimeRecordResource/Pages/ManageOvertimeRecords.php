<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\OvertimeRecordResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\OvertimeRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOvertimeRecords extends ManageRecords
{
    protected static string $resource = OvertimeRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
