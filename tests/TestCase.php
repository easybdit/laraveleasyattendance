<?php

namespace Easybdit\LaravelEasyAttendance\Tests;

use Easybdit\LaravelEasyAttendance\LaravelEasyAttendanceServiceProvider;
use Easybdit\LaravelEasyAttendance\Tests\Fixtures\TestPanelProvider;
use Easybdit\LaravelEasyAttendance\Tests\Fixtures\User;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\PanelProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A real host app defines its own named 'login' route — the auth
        // middleware redirects there for a guest. The bare testbench app
        // doesn't have one, so stub it just enough for that redirect to
        // resolve instead of blowing up with RouteNotFoundException.
        Route::get('/login', fn () => 'login')->name('login');
    }

    protected function getPackageProviders($app): array
    {
        $providers = [LaravelEasyAttendanceServiceProvider::class];

        // filament/filament is a suggested, not required, dependency —
        // only registered here (for FilamentTest) when it's actually
        // installed, same as a consuming app would only add it once
        // they've composer required Filament themselves. Testbench
        // doesn't run Laravel's package auto-discovery (no
        // bootstrap/cache/packages.php gets built), so every one of
        // Filament's sub-package providers — normally auto-discovered
        // in a real app via each package's own composer.json — has to
        // be listed by hand here too, not just the top-level one.
        if (class_exists(PanelProvider::class)) {
            $providers = [
                ...$providers,
                SupportServiceProvider::class,
                ActionsServiceProvider::class,
                NotificationsServiceProvider::class,
                SchemasServiceProvider::class,
                InfolistsServiceProvider::class,
                FormsServiceProvider::class,
                TablesServiceProvider::class,
                WidgetsServiceProvider::class,
                FilamentServiceProvider::class,
                LivewireServiceProvider::class,
                TestPanelProvider::class,
            ];
        }

        return $providers;
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * Defaults chosen so the suite runs out of the box against sqlite
     * in-memory (the common case everywhere except a sandbox without
     * pdo_sqlite) while staying overridable via real env vars for any
     * environment that needs a different driver — no repo file to edit
     * either way. See CONTRIBUTING.md.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => env('DB_CONNECTION', 'sqlite'),
            'database' => env('DB_DATABASE', ':memory:'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('attendance.subject_model', User::class);
        $app['config']->set('attendance.features.corrections', true);
        $app['config']->set('attendance.features.device_sync', true);
        $app['config']->set('attendance.features.employees', true);
        $app['config']->set('attendance.features.shifts', true);
        $app['config']->set('attendance.features.holidays', true);
        $app['config']->set('attendance.features.leave', true);
        $app['config']->set('attendance.features.summaries', true);
        $app['config']->set('attendance.features.salary', true);
        $app['config']->set('attendance.features.overtime', true);
        $app['config']->set('attendance.features.special_working_days', true);
        $app['config']->set('attendance.features.departments', true);
    }
}
