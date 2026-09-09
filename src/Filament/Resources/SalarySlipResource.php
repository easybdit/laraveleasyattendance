<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources;

use BackedEnum;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\RequiresFeature;
use Easybdit\LaravelEasyAttendance\Filament\Resources\SalarySlipResource\Pages\ManageSalarySlips;
use Easybdit\LaravelEasyAttendance\Models\SalarySlip;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Salary slips are normally produced by `php artisan attendance:generate-salary`
 * from a month's attendance summaries — this resource is for reviewing them
 * and making the occasional manual adjustment, not for building payroll by
 * hand every month.
 */
class SalarySlipResource extends Resource
{
    use RequiresFeature;

    protected static ?string $model = SalarySlip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'HR & Payroll';

    protected static ?int $navigationSort = 29;

    protected static function requiredFeature(): ?string
    {
        return 'salary';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')
                ->relationship('employee', 'name')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('year')
                ->numeric()
                ->required(),
            TextInput::make('month')
                ->numeric()
                ->minValue(1)
                ->maxValue(12)
                ->required(),
            TextInput::make('basic_salary')
                ->numeric()
                ->minValue(0)
                ->required(),
            KeyValue::make('allowances')
                ->keyLabel('Allowance')
                ->valueLabel('Amount')
                ->columnSpanFull(),
            TextInput::make('present_days')->numeric()->minValue(0)->default(0),
            TextInput::make('absent_days')->numeric()->minValue(0)->default(0),
            TextInput::make('late_days')->numeric()->minValue(0)->default(0),
            TextInput::make('leave_days')->numeric()->minValue(0)->default(0),
            TextInput::make('overtime_hours')->numeric()->minValue(0)->default(0),
            TextInput::make('overtime_amount')->numeric()->minValue(0)->default(0),
            TextInput::make('special_pay_amount')->numeric()->minValue(0)->default(0),
            TextInput::make('deduction_amount')->numeric()->minValue(0)->default(0),
            TextInput::make('net_salary')
                ->numeric()
                ->minValue(0)
                ->required(),
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
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('month')
                    ->sortable(),
                TextColumn::make('present_days')
                    ->label('Present')
                    ->toggleable(),
                TextColumn::make('absent_days')
                    ->label('Absent')
                    ->toggleable(),
                TextColumn::make('overtime_amount')
                    ->label('OT amount')
                    ->money()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('net_salary')
                    ->label('Net salary')
                    ->money()
                    ->sortable(),
                TextColumn::make('generated_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('year', 'desc')
            ->filters([
                SelectFilter::make('month')
                    ->options(array_combine(range(1, 12), array_map('strval', range(1, 12)))),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSalarySlips::route('/'),
        ];
    }
}
