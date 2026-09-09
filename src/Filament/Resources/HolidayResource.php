<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources;

use BackedEnum;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\RequiresFeature;
use Easybdit\LaravelEasyAttendance\Filament\Resources\HolidayResource\Pages\ManageHolidays;
use Easybdit\LaravelEasyAttendance\Models\Holiday;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HolidayResource extends Resource
{
    use RequiresFeature;

    protected static ?string $model = Holiday::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'HR & Payroll';

    protected static ?int $navigationSort = 24;

    protected static function requiredFeature(): ?string
    {
        return 'holidays';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            DatePicker::make('date')
                ->required()
                ->native(false),
            Toggle::make('is_recurring_yearly')
                ->label('Repeats every year')
                ->helperText('Same month/day counts as a holiday in any year, regardless of the year stored above.')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_recurring_yearly')
                    ->label('Yearly')
                    ->boolean(),
            ])
            ->defaultSort('date')
            ->filters([
                TernaryFilter::make('is_recurring_yearly')
                    ->label('Recurring yearly'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHolidays::route('/'),
        ];
    }
}
