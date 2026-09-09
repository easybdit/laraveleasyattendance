<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources;

use BackedEnum;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\HasPendingBadge;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\RequiresFeature;
use Easybdit\LaravelEasyAttendance\Filament\Resources\OvertimeRecordResource\Pages\ManageOvertimeRecords;
use Easybdit\LaravelEasyAttendance\Models\OvertimeRecord;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class OvertimeRecordResource extends Resource
{
    use HasPendingBadge;
    use RequiresFeature;

    protected static ?string $model = OvertimeRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'HR & Payroll';

    protected static ?int $navigationSort = 27;

    protected static function requiredFeature(): ?string
    {
        return 'overtime';
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
                ->native(false),
            TimePicker::make('shift_end_time')
                ->seconds(false),
            TimePicker::make('actual_out_time')
                ->seconds(false),
            TextInput::make('ot_hours')
                ->label('OT hours')
                ->numeric()
                ->minValue(0)
                ->required(),
            TextInput::make('ot_rate')
                ->label('Hourly rate')
                ->numeric()
                ->minValue(0)
                ->required(),
            TextInput::make('ot_amount')
                ->label('Amount')
                ->numeric()
                ->minValue(0)
                ->required(),
            Hidden::make('source')
                ->default('manual'),
            Hidden::make('status')
                ->default('pending'),
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
                TextColumn::make('ot_hours')
                    ->label('Hours')
                    ->sortable(),
                TextColumn::make('ot_amount')
                    ->label('Amount')
                    ->money()
                    ->sortable(),
                TextColumn::make('source')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (OvertimeRecord $record) => $record->status === 'pending')
                    ->action(function (OvertimeRecord $record) {
                        $record->approve(Auth::id());
                        static::forgetPendingBadgeCache();
                    }),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (OvertimeRecord $record) => $record->status === 'pending')
                    ->action(function (OvertimeRecord $record) {
                        $record->reject(Auth::id());
                        static::forgetPendingBadgeCache();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOvertimeRecords::route('/'),
        ];
    }
}
