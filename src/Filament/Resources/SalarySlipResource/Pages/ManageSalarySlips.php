<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\SalarySlipResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\SalarySlipResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSalarySlips extends ManageRecords
{
    protected static string $resource = SalarySlipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
