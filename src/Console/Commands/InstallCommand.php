<?php

namespace Easybdit\LaravelEasyAttendance\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'attendance:install {--force : Overwrite existing published config}';

    protected $description = 'Publish config and run migrations for LaravelEasyAttendance';

    public function handle(): int
    {
        $this->components->info('Installing LaravelEasyAttendance...');

        $this->call('vendor:publish', [
            '--tag' => 'attendance-config',
            '--force' => (bool) $this->option('force'),
        ]);

        if ($this->components->confirm('Run migrations now?', true)) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->components->info('Done. Next steps:');
        $this->line('  1. Add the Easybdit\\LaravelEasyAttendance\\Traits\\HasAttendance trait to your subject model (default: your User model).');
        $this->line('  2. Set ATTENDANCE_SUBJECT_MODEL in .env if attendance belongs to a different model (e.g. Employee).');
        $this->line('  3. POST /attendance/check-in and /attendance/check-out are ready — or call $user->checkIn() / ->checkOut() directly.');

        return self::SUCCESS;
    }
}
