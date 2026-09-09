<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Feature;

use Easybdit\LaravelEasyAttendance\Filament\EasyAttendancePlugin;
use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceCorrectionResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceCorrectionResource\Pages\ManageAttendanceCorrections;
use Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource\Pages\ManageEmployees;
use Easybdit\LaravelEasyAttendance\Models\AttendanceCorrection;
use Easybdit\LaravelEasyAttendance\Tests\Fixtures\User;
use Easybdit\LaravelEasyAttendance\Tests\TestCase;
use Filament\Facades\Filament;
use Filament\Panel;
use Livewire\Livewire;

/**
 * Exercises the optional Filament module against a real Filament panel
 * (see Tests\Fixtures\TestPanelProvider) — registered only when
 * filament/filament is actually installed (it's a suggested, not
 * required, dependency of the package itself).
 */
class FilamentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Panel::class)) {
            $this->markTestSkipped('filament/filament is not installed.');
        }

        // The fixture User doesn't implement FilamentUser, so panel access
        // is only granted in a 'local' environment — see
        // Filament\Http\Middleware\Authenticate.
        config(['app.env' => 'local']);
    }

    public function test_plugin_registers_every_resource_on_the_panel(): void
    {
        $resources = Filament::getPanel('admin')->getResources();

        $this->assertContains(EmployeeResource::class, $resources);
        $this->assertContains(AttendanceCorrectionResource::class, $resources);
        $this->assertSame('easy-attendance', EasyAttendancePlugin::make()->getId());
    }

    public function test_resource_is_hidden_and_inaccessible_when_its_feature_flag_is_off(): void
    {
        config(['attendance.features.employees' => false]);

        $this->assertFalse(EmployeeResource::canAccess());
    }

    public function test_resource_is_accessible_when_its_feature_flag_is_on(): void
    {
        config(['attendance.features.employees' => true]);

        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.com']));

        $this->assertTrue(EmployeeResource::canAccess());
    }

    public function test_employee_can_be_created_through_the_filament_form(): void
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com']);
        $this->actingAs($user);

        Livewire::test(ManageEmployees::class)
            ->callAction('create', data: [
                'employee_code' => 'E-100',
                'name' => 'Jane Doe',
                'basic_salary' => 50000,
                'status' => 'active',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('easyattendance_employees', [
            'employee_code' => 'E-100',
            'name' => 'Jane Doe',
        ]);
    }

    public function test_approving_a_correction_through_the_filament_action_creates_punches(): void
    {
        $subject = User::create(['name' => 'Employee One', 'email' => 'e1@example.com']);
        $reviewer = User::create(['name' => 'Reviewer', 'email' => 'reviewer@example.com']);
        $this->actingAs($reviewer);

        $correction = AttendanceCorrection::create([
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'date' => '2026-01-05',
            'requested_in' => '09:00:00',
            'requested_out' => '18:00:00',
            'reason' => 'Forgot to punch',
            'status' => 'pending',
        ]);

        Livewire::test(ManageAttendanceCorrections::class)
            ->callTableAction('approve', $correction);

        $this->assertSame('approved', $correction->fresh()->status);
        $this->assertDatabaseHas('easyattendance_attendances', [
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'type' => 'check_in',
        ]);
    }
}
