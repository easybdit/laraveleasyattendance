<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources;

use BackedEnum;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\RequiresFeature;
use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceSummaryResource\Pages\ManageAttendanceSummaries;
use Easybdit\LaravelEasyAttendance\Models\AttendanceSummary;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Read-only — one row per employee/date, built by
 * `php artisan attendance:build-summaries` (or on demand from
 * AttendanceSummaryService) from the raw punch log plus Shift/Leave/Holiday.
 * Editing a summary directly would just be overwritten the next time it's
 * rebuilt, so this resource is view/list only — no create, edit or delete.
 */
class AttendanceSummaryResource extends Resource
{
    use RequiresFeature;

    protected static ?string $model = AttendanceSummary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 3;

    protected static function requiredFeature(): ?string
    {
        return 'summaries';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('employee.name')
                ->label('Employee'),
            TextEntry::make('date')->date(),
            TextEntry::make('status')->badge(),
            TextEntry::make('first_in')->dateTime()->placeholder('—'),
            TextEntry::make('last_out')->dateTime()->placeholder('—'),
            TextEntry::make('work_hours')->placeholder('—'),
            TextEntry::make('late_minutes'),
            TextEntry::make('ot_minutes')->label('OT minutes'),
            TextEntry::make('shift_start')->placeholder('—'),
            TextEntry::make('shift_end')->placeholder('—'),
            TextEntry::make('shift_source')->placeholder('—'),
            TextEntry::make('holiday_name')->placeholder('—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'present' => 'success',
                        'late' => 'warning',
                        'absent' => 'danger',
                        'leave' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('first_in')
                    ->time()
                    ->placeholder('—'),
                TextColumn::make('last_out')
                    ->time()
                    ->placeholder('—'),
                TextColumn::make('late_minutes')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ot_minutes')
                    ->label('OT min')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'present' => 'Present',
                        'late' => 'Late',
                        'absent' => 'Absent',
                        'leave' => 'Leave',
                        'holiday' => 'Holiday',
                        'day_off' => 'Day off',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAttendanceSummaries::route('/'),
        ];
    }
}
