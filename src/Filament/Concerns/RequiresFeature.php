<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Concerns;

/**
 * Gates a Resource behind the same `attendance.features.*` flag its
 * migrations/routes already check — a resource for a module you haven't
 * enabled has no table to read from, so it should stay out of the
 * navigation and refuse direct URL access too, not just error on load.
 *
 * requiredFeature() returning null means "always available" (Attendance
 * itself has no dedicated feature flag — it's the package's core).
 */
trait RequiresFeature
{
    protected static function requiredFeature(): ?string
    {
        return null;
    }

    public static function canAccess(): bool
    {
        $feature = static::requiredFeature();

        if ($feature !== null && ! config("attendance.features.{$feature}", false)) {
            return false;
        }

        return parent::canAccess();
    }
}
