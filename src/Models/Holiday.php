<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Carbon\Carbon;
use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'holidays';
    }

    protected $fillable = ['name', 'date', 'is_recurring_yearly'];

    protected $casts = [
        'date' => 'date',
        'is_recurring_yearly' => 'boolean',
    ];

    /**
     * Is $date a holiday — either an exact match, or the same month/day
     * as a recurring-yearly one (a different year is fine).
     *
     * Named onDate(), not on() — Eloquent\Model already declares a static
     * on($connection) for choosing a DB connection; overriding it with an
     * incompatible signature is a fatal error, not just a shadow.
     */
    public static function onDate(string $date): ?self
    {
        $carbon = Carbon::parse($date);

        // whereDate(), not where() — a plain `date`-cast attribute gets
        // written through fromDateTime() using the connection's full
        // datetime format (e.g. "2026-09-01 00:00:00"). MySQL silently
        // truncates that back to just the date on a DATE column; SQLite
        // stores it verbatim, so an exact-string where('date', ...) only
        // ever matches on MySQL. whereDate() compares just the date part
        // at the SQL level regardless of which of those got stored.
        return static::whereDate('date', $carbon->toDateString())
            ->orWhere(function ($q) use ($carbon) {
                $q->where('is_recurring_yearly', true)
                    ->whereMonth('date', $carbon->month)
                    ->whereDay('date', $carbon->day);
            })
            ->first();
    }
}
