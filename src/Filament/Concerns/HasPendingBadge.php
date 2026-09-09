<?php

namespace Easybdit\LaravelEasyAttendance\Filament\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Navigation badge showing this resource's pending-status count (Leave,
 * OvertimeRecord, AttendanceCorrection). Filament re-renders the sidebar
 * navigation on every page load, so a naive count() here runs on every
 * single request across the whole panel — cached for a short window
 * (30s: fresh enough for an approval queue, but spares the DB on a busy
 * multi-admin panel) rather than left to hit the table every time.
 */
trait HasPendingBadge
{
    public static function getNavigationBadge(): ?string
    {
        $count = Cache::remember(
            'easy-attendance:filament-badge:'.static::class,
            now()->addSeconds(30),
            fn () => static::getEloquentQuery()->where('status', 'pending')->count(),
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Called after an approve()/reject() action so the badge doesn't sit
     * on a stale count for the rest of its 30s window — the action that
     * just changed the count is the one place that can afford to pay for
     * a fresh read immediately instead of waiting out the cache.
     */
    public static function forgetPendingBadgeCache(): void
    {
        Cache::forget('easy-attendance:filament-badge:'.static::class);
    }
}
