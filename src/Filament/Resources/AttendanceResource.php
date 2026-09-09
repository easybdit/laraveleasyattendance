<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources;

use BackedEnum;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\HasSubjectPicker;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\RequiresFeature;
use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceResource\Pages\ManageAttendances;
use Easybdit\LaravelEasyAttendance\Models\Attendance;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Punches are usually created by check-in/out calls or device sync, not by
 * hand — but this resource still gives HR a way to inspect and, when
 * corrections aren't enabled, manually add/adjust a punch. The subject
 * picker deliberately targets a single configured model
 * (config('attendance.subject_model')) rather than every possible morph
 * target — that's how the overwhelming majority of installs use it (one
 * subject model app-wide), and keeps the form a plain searchable Select
 * instead of a two-step "pick a type, then pick a record" UI.
 */
class AttendanceResource extends Resource
{
    use HasSubjectPicker;
    use RequiresFeature;

    protected static ?string $model = Attendance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $subjectModel = static::subjectModel();

        return $schema->components([
            Hidden::make('subject_type')
                ->default($subjectModel),
            Select::make('subject_id')
                ->label('Subject')
                ->searchable()
                ->required()
                ->getSearchResultsUsing(fn (string $search) => static::subjectOptions($subjectModel, $search))
                ->getOptionLabelUsing(fn ($value) => static::subjectLabel($subjectModel::find($value))),
            DateTimePicker::make('time')
                ->required()
                ->seconds(false),
            Select::make('type')
                ->options([
                    'check_in' => 'Check in',
                    'check_out' => 'Check out',
                ])
                ->required(),
            Select::make('source')
                ->options([
                    'api' => 'API',
                    'manual' => 'Manual',
                    'device' => 'Device',
                    'correction' => 'Correction',
                ])
                ->default('manual')
                ->required(),
            Toggle::make('is_manual')
                ->label('Manual entry')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject_id')
                    ->label('Subject')
                    ->state(fn (Attendance $record) => static::subjectLabel($record->subject))
                    ->searchable(false),
                TextColumn::make('time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'check_in' ? 'success' : 'warning'),
                TextColumn::make('source')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_manual')
                    ->label('Manual')
                    ->boolean(),
                TextColumn::make('device.name')
                    ->label('Device')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('time', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'check_in' => 'Check in',
                        'check_out' => 'Check out',
                    ]),
                SelectFilter::make('source')
                    ->options([
                        'api' => 'API',
                        'manual' => 'Manual',
                        'device' => 'Device',
                        'correction' => 'Correction',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAttendances::route('/'),
        ];
    }
}
