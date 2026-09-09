<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Resources;

use BackedEnum;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\HasSubjectPicker;
use Easybdit\LaravelEasyAttendance\Filament\Concerns\RequiresFeature;
use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceCorrectionResource\Pages\ManageAttendanceCorrections;
use Easybdit\LaravelEasyAttendance\Models\AttendanceCorrection;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AttendanceCorrectionResource extends Resource
{
    use HasSubjectPicker;
    use RequiresFeature;

    protected static ?string $model = AttendanceCorrection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 2;

    protected static function requiredFeature(): ?string
    {
        return 'corrections';
    }

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
            Hidden::make('submitted_by')
                ->default(fn () => Auth::id()),
            DatePicker::make('date')
                ->required()
                ->native(false),
            TimePicker::make('requested_in')
                ->label('Requested check-in')
                ->seconds(false),
            TimePicker::make('requested_out')
                ->label('Requested check-out')
                ->seconds(false),
            Textarea::make('reason')
                ->required()
                ->columnSpanFull(),
            Hidden::make('status')
                ->default('pending'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject_id')
                    ->label('Subject')
                    ->state(fn (AttendanceCorrection $record) => static::subjectLabel($record->subject)),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('requested_in')
                    ->label('Requested in')
                    ->placeholder('—'),
                TextColumn::make('requested_out')
                    ->label('Requested out')
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
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
                    ->visible(fn (AttendanceCorrection $record) => $record->status === 'pending')
                    ->action(fn (AttendanceCorrection $record) => $record->approve(Auth::id())),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AttendanceCorrection $record) => $record->status === 'pending')
                    ->action(fn (AttendanceCorrection $record) => $record->reject(Auth::id())),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAttendanceCorrections::route('/'),
        ];
    }
}
