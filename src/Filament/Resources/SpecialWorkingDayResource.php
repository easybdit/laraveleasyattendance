<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources;

use BackedEnum;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\RequiresFeature;
use Easybdit\LaravelEasyAttendance\Filament\Resources\SpecialWorkingDayResource\Pages\ManageSpecialWorkingDays;
use Easybdit\LaravelEasyAttendance\Models\SpecialWorkingDay;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SpecialWorkingDayResource extends Resource
{
    use RequiresFeature;

    protected static ?string $model = SpecialWorkingDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|\UnitEnum|null $navigationGroup = 'HR & Payroll';

    protected static ?int $navigationSort = 28;

    protected static function requiredFeature(): ?string
    {
        return 'special_working_days';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')
                ->relationship('employee', 'name')
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('date')
                ->required()
                ->native(false)
                ->helperText('Whether this counts as a holiday or a day-off is detected automatically from the date — no need to set it by hand.'),
            Toggle::make('is_payable')
                ->default(true),
            TextInput::make('payment_amount')
                ->numeric()
                ->minValue(0)
                ->helperText('Leave empty to use the configured default rate for this type of day.'),
            Textarea::make('note')
                ->columnSpanFull(),
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
                TextColumn::make('type')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_payable')
                    ->boolean(),
                TextColumn::make('payment_amount')
                    ->money()
                    ->placeholder('Default rate'),
            ])
            ->defaultSort('date', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSpecialWorkingDays::route('/'),
        ];
    }
}
