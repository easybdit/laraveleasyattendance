<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources;

use BackedEnum;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\RequiresFeature;
use Easybdit\LaravelEasyAttendance\Filament\Resources\ShiftResource\Pages\ManageShifts;
use Easybdit\LaravelEasyAttendance\Models\Shift;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShiftResource extends Resource
{
    use RequiresFeature;

    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'HR & Payroll';

    protected static ?int $navigationSort = 23;

    protected static function requiredFeature(): ?string
    {
        return 'shifts';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TimePicker::make('start_time')
                ->seconds(false)
                ->required(),
            TimePicker::make('end_time')
                ->seconds(false)
                ->required(),
            TextInput::make('late_grace_minutes')
                ->label('Late grace (minutes)')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            CheckboxList::make('off_days')
                ->options([
                    'Sunday' => 'Sunday',
                    'Monday' => 'Monday',
                    'Tuesday' => 'Tuesday',
                    'Wednesday' => 'Wednesday',
                    'Thursday' => 'Thursday',
                    'Friday' => 'Friday',
                    'Saturday' => 'Saturday',
                ])
                ->columns(4)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_time')
                    ->time(),
                TextColumn::make('end_time')
                    ->time(),
                TextColumn::make('late_grace_minutes')
                    ->label('Grace (min)'),
                TextColumn::make('off_days')
                    ->badge()
                    ->separator(','),
                TextColumn::make('assignments_count')
                    ->counts('assignments')
                    ->label('Employees'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageShifts::route('/'),
        ];
    }
}
