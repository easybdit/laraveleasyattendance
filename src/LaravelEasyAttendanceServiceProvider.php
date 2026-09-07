<?php

namespace Easybdit\LaravelEasyAttendance;

use Easybdit\LaravelEasyAttendance\Console\Commands\BuildAttendanceSummaries;
use Easybdit\LaravelEasyAttendance\Console\Commands\GenerateSalary;
use Easybdit\LaravelEasyAttendance\Console\Commands\InstallCommand;
use Easybdit\LaravelEasyAttendance\Console\Commands\SyncAttendanceDevices;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LaravelEasyAttendanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/attendance.php', 'attendance');
    }

    public function boot(): void
    {
        // Auto-load migrations so `php artisan migrate` works right after
        // `composer require` with zero extra steps. Publishing (below) is
        // for apps that want to customize the schema.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/attendance.php' => config_path('attendance.php'),
            ], 'attendance-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'attendance-migrations');

            $this->commands([
                InstallCommand::class,
                SyncAttendanceDevices::class,
                BuildAttendanceSummaries::class,
                GenerateSalary::class,
            ]);
        }

        $this->registerRoutes();
        $this->registerAdmsRoutes();
    }

    protected function registerRoutes(): void
    {
        if (! config('attendance.routes.enabled', true)) {
            return;
        }

        Route::prefix(config('attendance.routes.prefix', 'attendance'))
            ->middleware(config('attendance.routes.middleware', ['web', 'auth']))
            ->group(__DIR__.'/../routes/web.php');
    }

    /**
     * Deliberately registered with NO middleware group at all — not even
     * 'web' — because a ZK device can't carry a session or a CSRF token.
     * Skipping 'web' means Laravel's CSRF middleware never sees these
     * routes in the first place, so (unlike routes/web.php-based ADMS
     * receivers elsewhere) there's nothing to exempt in bootstrap/app.php.
     */
    protected function registerAdmsRoutes(): void
    {
        if (! config('attendance.features.device_sync', false)) {
            return;
        }

        Route::group([], __DIR__.'/../routes/adms.php');
    }
}
