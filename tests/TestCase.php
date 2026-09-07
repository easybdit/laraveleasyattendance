<?php

namespace Easybdit\LaravelEasyAttendance\Tests;

use Easybdit\LaravelEasyAttendance\LaravelEasyAttendanceServiceProvider;
use Easybdit\LaravelEasyAttendance\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
        return [LaravelEasyAttendanceServiceProvider::class];
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
    }
}
