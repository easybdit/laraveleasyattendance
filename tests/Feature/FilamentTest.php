<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Feature;

use Easybdit\LaravelEasyAttendance\Filament\EasyAttendancePlugin;
use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceCorrectionResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\AttendanceCorrectionResource\Pages\ManageAttendanceCorrections;
use Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource\Pages\ManageEmployees;
use Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource\Pages\ViewEmployee;
use Easybdit\LaravelEasyAttendance\Filament\Resources\EmployeeResource\RelationManagers\LeavesRelationManager;
use Easybdit\LaravelEasyAttendance\Filament\Resources\LeaveResource;
use Easybdit\LaravelEasyAttendance\Filament\Resources\LeaveResource\Pages\ManageLeaves;
use Easybdit\LaravelEasyAttendance\Filament\Widgets\AttendanceOverviewWidget;
use Easybdit\LaravelEasyAttendance\Models\AttendanceCorrection;
use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\Leave;
use Easybdit\LaravelEasyAttendance\Models\LeaveType;
use Easybdit\LaravelEasyAttendance\Tests\Fixtures\User;
use Easybdit\LaravelEasyAttendance\Tests\TestCase;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Cache;
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

        // Badge/widget counts are cached for 30s (HasPendingBadge,
        // AttendanceOverviewWidget) — a fresh cache per test keeps one
        // test's counts from leaking into the next.
        Cache::flush();
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

    public function test_navigation_badge_reflects_pending_count_and_updates_after_approval(): void
    {
        $employee = Employee::create([
            'employee_code' => 'E-200',
            'name' => 'Badge Test Employee',
            'basic_salary' => 1000,
        ]);
        $leaveType = LeaveType::create(['name' => 'Casual']);
        $reviewer = User::create(['name' => 'Reviewer', 'email' => 'reviewer2@example.com']);
        $this->actingAs($reviewer);

        // Deliberately not asserting the badge before this — calling
        // getNavigationBadge() would cache today's (zero) count for 30s,
        // and the whole point of the cache is that it doesn't get busted
        // by a plain Leave::create() the way it does by the resource's
        // own approve()/reject() action below.
        $leave = Leave::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-02',
            'reason' => 'Personal',
            'status' => 'pending',
        ]);

        $this->assertSame('1', LeaveResource::getNavigationBadge());

        Livewire::test(ManageLeaves::class)
            ->callTableAction('approve', $leave);

        // forgetPendingBadgeCache() runs inside the approve action itself,
        // so the badge is fresh immediately — no need to wait out the 30s
        // cache window.
        $this->assertNull(LeaveResource::getNavigationBadge());
    }

    public function test_dashboard_widget_renders_for_an_authenticated_user(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'widget-admin@example.com']));

        Livewire::test(AttendanceOverviewWidget::class)
            ->assertOk()
            ->assertSee('Pending approvals');
    }

    public function test_employee_leaves_relation_manager_lists_and_approves_from_the_profile_page(): void
    {
        $employee = Employee::create([
            'employee_code' => 'E-300',
            'name' => 'Relation Manager Employee',
            'basic_salary' => 1000,
        ]);
        $leaveType = LeaveType::create(['name' => 'Sick']);
        $leave = Leave::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-01',
            'reason' => 'Fever',
            'status' => 'pending',
        ]);
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'rm-admin@example.com']));

        Livewire::test(ViewEmployee::class, ['record' => $employee->getKey()])
            ->assertOk();

        Livewire::test(LeavesRelationManager::class, [
            'ownerRecord' => $employee,
            'pageClass' => ViewEmployee::class,
        ])
            ->assertCanSeeTableRecords([$leave])
            ->callTableAction('approve', $leave);

        $this->assertSame('approved', $leave->fresh()->status);
    }
}
