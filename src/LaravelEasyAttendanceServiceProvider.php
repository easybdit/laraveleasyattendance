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

        // 'attendance::print.payslip' etc. — auto-loaded so the print
        // routes work out of the box; publishable for restyling/branding.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'attendance');

        // trans('attendance::notifications.late.subject') etc. — English
        // shipped, publishable so you can add/override other locales
        // without forking the package.
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'attendance');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/attendance.php' => config_path('attendance.php'),
            ], 'attendance-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'attendance-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/attendance'),
            ], 'attendance-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => lang_path('vendor/attendance'),
            ], 'attendance-lang');

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
     * Deliberately registered with NO 'web' middleware — a ZK device
     * can't carry a session or a CSRF token, so skipping 'web' means
     * Laravel's CSRF middleware never sees these routes in the first
     * place (unlike routes/web.php-based ADMS receivers elsewhere,
     * nothing to exempt in bootstrap/app.php for this to work). `throttle`
     * doesn't need a session either, so it's the one middleware still
     * applied — see config('attendance.device_sync.adms_throttle'): these
     * routes are public and unauthenticated by protocol necessity, so
     * rate limiting is the one built-in guard against a request flood.
     */
    protected function registerAdmsRoutes(): void
    {
        if (! config('attendance.features.device_sync', false)) {
            return;
        }

        Route::middleware(config('attendance.device_sync.adms_throttle', 'throttle:60,1'))
            ->group(__DIR__.'/../routes/adms.php');
    }
}
