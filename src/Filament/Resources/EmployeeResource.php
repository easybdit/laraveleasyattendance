<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources;

use BackedEnum;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\RequiresFeature;
use Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource\Pages\ManageEmployees;
use Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource\Pages\ViewEmployee;
use Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource\RelationManagers\LeavesRelationManager;
use Easybdit\LaravelEasyAttendance\Models\Employee;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    use RequiresFeature;

    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'HR & Payroll';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function requiredFeature(): ?string
    {
        return 'employees';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('employee_code')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->email()
                ->maxLength(255),
            TextInput::make('phone')
                ->tel()
                ->maxLength(255),
            Select::make('department_id')
                ->relationship('department', 'name')
                ->searchable()
                ->preload()
                ->visible(fn () => (bool) config('attendance.features.departments'))
                ->live(),
            Select::make('designation_id')
                ->label('Designation')
                ->relationship(
                    'designationRecord',
                    'name',
                    fn ($query, $get) => $get('department_id') ? $query->where('department_id', $get('department_id')) : $query,
                )
                ->searchable()
                ->preload()
                ->visible(fn () => (bool) config('attendance.features.departments')),
            TextInput::make('designation')
                ->helperText('Free-text job title — used when the Department/Designation module is off.')
                ->maxLength(255)
                ->visible(fn () => ! config('attendance.features.departments')),
            TextInput::make('device_user_id')
                ->label('Device PIN')
                ->helperText('The numeric user id registered on the biometric device, used to match incoming punches.')
                ->maxLength(255),
            TextInput::make('basic_salary')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            KeyValue::make('allowances')
                ->keyLabel('Allowance')
                ->valueLabel('Amount')
                ->columnSpanFull(),
            DatePicker::make('joined_at')
                ->native(false),
            Select::make('status')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ])
                ->default('active')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('designationRecord.name')
                    ->label('Designation')
                    ->placeholder(fn ($record) => $record->designation)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('basic_salary')
                    ->money()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('joined_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->visible(fn () => (bool) config('attendance.features.departments')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return config('attendance.features.leave')
            ? [LeavesRelationManager::class]
            : [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployees::route('/'),
            'view' => ViewEmployee::route('/{record}'),
        ];
    }
}
