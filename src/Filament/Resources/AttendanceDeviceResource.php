<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources;

use BackedEnum;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\RequiresFeature;
use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceDeviceResource\Pages\ManageAttendanceDevices;
use Easybdit\LaravelEasyAttendance\Models\AttendanceDevice;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceDeviceResource extends Resource
{
    use RequiresFeature;

    protected static ?string $model = AttendanceDevice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 4;

    protected static function requiredFeature(): ?string
    {
        return 'device_sync';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('ip')
                ->label('IP address')
                ->helperText('Set this for pull-mode (the server connects out to the device).')
                ->maxLength(255),
            TextInput::make('port')
                ->numeric(),
            TextInput::make('comm_key')
                ->label('Comm key')
                ->password()
                ->revealable(),
            TextInput::make('serial_number')
                ->helperText('Set this for push/ADMS mode (the device dials home to this app).')
                ->maxLength(255),
            TextInput::make('model')
                ->maxLength(255),
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('connection_mode')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_online')
                    ->label('Online')
                    ->boolean(),
                TextColumn::make('ip')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('serial_number')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('last_seen_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('sync_fail_count')
                    ->label('Fail count')
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => ManageAttendanceDevices::route('/'),
        ];
    }
}
