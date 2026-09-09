<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Fixtures;

use Easybdit\LaravelEasyAttendance\Filament\EasyAttendancePlugin;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * Minimal panel used only by the test suite to exercise
 * EasyAttendancePlugin against a real Filament panel — never shipped.
 */
class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->login()
            ->plugin(EasyAttendancePlugin::make());
    }
}
