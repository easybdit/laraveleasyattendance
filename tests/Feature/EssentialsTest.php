<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Feature;

use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\SalarySlip;
use Easybdit\LaravelEasyAttendance\Notifications\AttendanceMarkedLateNotification;
use Easybdit\LaravelEasyAttendance\Notifications\LeaveReviewedNotification;
use Easybdit\LaravelEasyAttendance\Services\AttendanceSummaryService;
use Easybdit\LaravelEasyAttendance\Services\EmployeeCsvImporter;
use Easybdit\LaravelEasyAttendance\Services\SalaryService;
use Easybdit\LaravelEasyAttendance\Tests\Fixtures\User;
use Easybdit\LaravelEasyAttendance\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

class EssentialsTest extends TestCase
{
    private function actor(): User
    {
        return User::create(['name' => 'Admin']);
    }

    private function csvUploadedFile(string $content, string $name = 'employees.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    // ── CSV export ───────────────────────────────────────────────────────────

    public function test_daily_report_can_be_exported_as_csv(): void
    {
        $admin = $this->actor();
        $employee = Employee::create(['employee_code' => 'E-700', 'name' => 'Tania', 'basic_salary' => 20000, 'status' => 'active']);
        $employee->checkIn(['time' => '2026-11-01 09:05:00']);
        (new AttendanceSummaryService)->buildOne($employee, '2026-11-01');

        $response = $this->actingAs($admin)->get('/attendance/reports/daily?date=2026-11-01&format=csv');

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('content-type'));
        $body = $response->streamedContent();
        $this->assertStringContainsString('Employee Code', $body);
        $this->assertStringContainsString('E-700', $body);
        $this->assertStringContainsString('Tania', $body);
    }

    public function test_salary_report_can_be_exported_as_csv(): void
    {
        $admin = $this->actor();
        $employee = Employee::create(['employee_code' => 'E-701', 'name' => 'Rakib', 'basic_salary' => 25000, 'allowances' => [], 'status' => 'active']);
        (new SalaryService)->generate($employee, 2026, 11);

        $response = $this->actingAs($admin)->get('/attendance/reports/salary?year=2026&month=11&format=csv');

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('Net Salary', $body);
        $this->assertStringContainsString('E-701', $body);
    }

    public function test_csv_export_escapes_a_cell_that_looks_like_a_spreadsheet_formula(): void
    {
        $admin = $this->actor();
        // A name starting with '=' would open as a live formula the
        // moment this CSV is opened in Excel/Sheets if left unescaped —
        // see Http\Controllers\Concerns\ExportsCsv::escapeCsvFormula().
        $employee = Employee::create(['employee_code' => 'E-702', 'name' => '=SUM(A1:A9)', 'basic_salary' => 20000, 'status' => 'active']);
        $employee->checkIn(['time' => '2026-11-01 09:05:00']);
        (new AttendanceSummaryService)->buildOne($employee, '2026-11-01');

        $response = $this->actingAs($admin)->get('/attendance/reports/daily?date=2026-11-01&format=csv');

        $body = $response->streamedContent();
        $this->assertStringContainsString("E-702,'=SUM(A1:A9)", $body);
        $this->assertStringNotContainsString('E-702,=SUM', $body);
    }

    public function test_daily_report_defaults_to_json_without_format_param(): void
    {
        $admin = $this->actor();

        $this->actingAs($admin)->getJson('/attendance/reports/daily?date=2026-11-01')
            ->assertOk()
            ->assertJsonStructure(['date', 'total', 'rows']);
    }

    // ── CSV import ───────────────────────────────────────────────────────────

    public function test_employee_csv_import_creates_valid_rows_and_reports_invalid_ones(): void
    {
        $admin = $this->actor();

        $csv = implode("\n", [
            'employee_code,name,basic_salary,status',
            'E-800,Nusrat,30000,active',
            ',Missing Code,25000,active', // invalid: employee_code required
        ]);

        $response = $this->actingAs($admin)->post('/attendance/employees/import', [
            'file' => $this->csvUploadedFile($csv),
        ]);

        $response->assertOk();
        $response->assertJson(['imported' => 1, 'skipped' => 1]);
        $this->assertNotNull(Employee::where('employee_code', 'E-800')->first());
        $this->assertCount(1, $response->json('errors'));
        $this->assertSame(3, $response->json('errors.0.row')); // header is row 1
    }

    public function test_employee_csv_import_maps_allowance_columns(): void
    {
        $importer = new EmployeeCsvImporter;

        $csv = implode("\n", [
            'employee_code,name,basic_salary,allowance_house_rent,allowance_medical',
            'E-801,Farhan,28000,5000,1000',
        ]);

        $result = $importer->import($this->csvUploadedFile($csv));

        $this->assertSame(1, $result['imported']);
        $employee = Employee::where('employee_code', 'E-801')->first();
        $this->assertEquals(['house_rent' => 5000.0, 'medical' => 1000.0], $employee->allowances);
    }

    public function test_employee_csv_import_skips_duplicate_employee_code_within_the_same_file(): void
    {
        $importer = new EmployeeCsvImporter;

        $csv = implode("\n", [
            'employee_code,name,basic_salary',
            'E-802,First,20000',
            'E-802,Second,20000',
        ]);

        $result = $importer->import($this->csvUploadedFile($csv));

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['skipped']);
    }

    // ── Notifications ────────────────────────────────────────────────────────

    public function test_attendance_marked_late_notification_can_be_sent(): void
    {
        Notification::fake();

        $recipient = $this->actor();
        $employee = Employee::create(['employee_code' => 'E-900', 'name' => 'Shanta', 'basic_salary' => 20000, 'status' => 'active']);

        $notification = new AttendanceMarkedLateNotification($employee, '2026-11-01', 45);
        $recipient->notify($notification);

        Notification::assertSentTo($recipient, AttendanceMarkedLateNotification::class, fn ($n) => $n->lateMinutes === 45);

        // toMail() must build without throwing, and mention the employee.
        $mail = $notification->toMail($recipient);
        $this->assertStringContainsString('Late attendance', $mail->subject);
        $this->assertTrue(collect($mail->introLines)->contains(fn ($line) => str_contains($line, 'Shanta')));

        $this->assertSame(['mail', 'database'], $notification->via($recipient));
        $this->assertArrayHasKey('late_minutes', $notification->toArray($recipient));
    }

    public function test_leave_reviewed_notification_content_reflects_status(): void
    {
        $employee = Employee::create(['employee_code' => 'E-901', 'name' => 'Kabir', 'basic_salary' => 20000, 'status' => 'active']);
        $leave = $employee->requestLeave(['start_date' => '2026-11-05', 'end_date' => '2026-11-05', 'reason' => 'sick']);
        $leave->approve();

        $notification = new LeaveReviewedNotification($leave->fresh());
        $mail = $notification->toMail($this->actor());

        $this->assertStringContainsString('approved', strtolower($mail->subject));
        $this->assertSame(['leave_id' => $leave->id, 'status' => 'approved', 'review_note' => null], $notification->toArray($this->actor()));
    }

    // ── Print views ──────────────────────────────────────────────────────────

    public function test_payslip_print_view_renders(): void
    {
        $admin = $this->actor();
        $employee = Employee::create(['employee_code' => 'E-902', 'name' => 'Print Test', 'basic_salary' => 20000, 'allowances' => [], 'status' => 'active']);
        $slip = (new SalaryService)->generate($employee, 2026, 11);

        $response = $this->actingAs($admin)->get("/attendance/salary/{$slip->id}/print");

        $response->assertOk();
        $response->assertSee('Print Test');
        $response->assertSee('Net Salary');
    }

    public function test_monthly_report_print_view_renders(): void
    {
        $admin = $this->actor();
        $employee = Employee::create(['employee_code' => 'E-903', 'name' => 'Grid Test', 'basic_salary' => 20000, 'status' => 'active']);
        $employee->checkIn(['time' => '2026-11-02 09:00:00']);
        (new AttendanceSummaryService)->buildOne($employee, '2026-11-02');

        $response = $this->actingAs($admin)->get('/attendance/reports/monthly/print?year=2026&month=11');

        $response->assertOk();
        $response->assertSee('Grid Test');
        $response->assertSee('November 2026');
    }
}
