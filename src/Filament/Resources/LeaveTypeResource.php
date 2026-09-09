<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources;

use BackedEnum;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\RequiresFeature;
use Easybdit\LaravelEasyAttendance\Filament\Resources\LeaveTypeResource\Pages\ManageLeaveTypes;
use Easybdit\LaravelEasyAttendance\Models\LeaveType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeaveTypeResource extends Resource
{
    use RequiresFeature;

    protected static ?string $model = LeaveType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'HR & Payroll';

    protected static ?int $navigationSort = 25;

    protected static function requiredFeature(): ?string
    {
        return 'leave';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('days_allowed_per_year')
                ->label('Days allowed per year')
                ->helperText('Leave empty for unlimited.')
                ->numeric()
                ->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('days_allowed_per_year')
                    ->label('Days / year')
                    ->placeholder('Unlimited')
                    ->sortable(),
                TextColumn::make('leaves_count')
                    ->counts('leaves')
                    ->label('Requests'),
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
            'index' => ManageLeaveTypes::route('/'),
        ];
    }
}
