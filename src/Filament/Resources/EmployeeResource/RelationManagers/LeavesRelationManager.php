<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource\RelationManagers;

use Easybdit\LaravelEasyAttendance\Models\Leave;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Lets HR see and act on one employee's leave history right from their
 * profile, instead of only from the flat, unfiltered LeaveResource list.
 * Same approve()/reject() actions as LeaveResource — this is a view onto
 * the same table, not a separate code path.
 */
class LeavesRelationManager extends RelationManager
{
    protected static string $relationship = 'leaves';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedCalendarDateRange;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('leave_type_id')
                ->label('Leave type')
                ->relationship('leaveType', 'name')
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('start_date')
                ->required()
                ->native(false),
            DatePicker::make('end_date')
                ->required()
                ->native(false)
                ->afterOrEqual('start_date'),
            Textarea::make('reason')
                ->required()
                ->columnSpanFull(),
            Hidden::make('status')
                ->default('pending'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                TextColumn::make('leaveType.name')
                    ->label('Type'),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('days')
                    ->state(fn (Leave $record) => $record->daysCount()),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('start_date', 'desc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Leave $record) => $record->status === 'pending')
                    ->action(fn (Leave $record) => $record->approve(Auth::id())),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Leave $record) => $record->status === 'pending')
                    ->action(fn (Leave $record) => $record->reject(Auth::id())),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
