<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource\Pages;

use Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * The only reason Employee has a dedicated page instead of staying pure
 * modal-CRUD like every other "Manage" resource: relation manager tabs
 * (LeavesRelationManager) can only render inside a real resource page,
 * not inside a modal. Editing still happens in the same modal as before —
 * this page is reached via the table's ViewAction, not by default.
 */
class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
