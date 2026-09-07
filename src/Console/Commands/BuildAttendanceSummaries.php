<?php

namespace Easybdit\LaravelEasyAttendance\Console\Commands;

use Easybdit\LaravelEasyAttendance\Services\AttendanceSummaryService;
use Illuminate\Console\Command;

class BuildAttendanceSummaries extends Command
{
    protected $signature = 'attendance:build-summaries {date? : Y-m-d, defaults to today}';

    protected $description = 'Build/rebuild every active employee\'s attendance summary for a date';

    public function handle(AttendanceSummaryService $service): int
    {
        $date = $this->argument('date') ?? now()->toDateString();

        $count = $service->buildForDate($date);

        $this->components->info("Built {$count} summary row(s) for {$date}.");

        return self::SUCCESS;
    }
}
