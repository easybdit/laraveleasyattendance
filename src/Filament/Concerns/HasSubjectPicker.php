<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Concerns;

/**
 * Shared searchable-Select helper for the polymorphic `subject` relation
 * (Attendance, AttendanceCorrection) — targets the single configured
 * config('attendance.subject_model') rather than every possible morph
 * target. See AttendanceResource's class docblock for why.
 */
trait HasSubjectPicker
{
    protected static function subjectModel(): string
    {
        return config('attendance.subject_model');
    }

    /**
     * @return array<int|string, string>
     */
    protected static function subjectOptions(string $subjectModel, string $search): array
    {
        return $subjectModel::query()
            ->when(
                $search,
                fn ($query) => $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
            )
            ->limit(50)
            ->get()
            ->mapWithKeys(fn ($model) => [$model->getKey() => static::subjectLabel($model)])
            ->all();
    }

    protected static function subjectLabel(mixed $model): string
    {
        if (! $model) {
            return '—';
        }

        return $model->name ?? $model->email ?? ('#'.$model->getKey());
    }
}
