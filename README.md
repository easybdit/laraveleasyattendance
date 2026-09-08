# Laravel Easy Attendance — Laravel Attendance Management & ZKTeco Biometric Integration

[![tests](https://github.com/easybdit/laraveleasyattendance/actions/workflows/tests.yml/badge.svg)](https://github.com/easybdit/laraveleasyattendance/actions/workflows/tests.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/easybdit/laraveleasyattendance.svg)](https://packagist.org/packages/easybdit/laraveleasyattendance)
[![Total Downloads](https://img.shields.io/packagist/dt/easybdit/laraveleasyattendance.svg)](https://packagist.org/packages/easybdit/laraveleasyattendance)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-FF2D20.svg)](https://laravel.com/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://opensource.org/licenses/MIT)

A complete **Laravel attendance management package** for employee check-in and check-out, biometric attendance, ZKTeco device synchronization, shifts, leave management, holidays, overtime, attendance reports, salary generation, and payroll workflows. Built for modern Laravel applications that need a flexible employee attendance and HR solution.

Built for **Laravel 11, Laravel 12, and Laravel 13** with **PHP 8.2+**, Laravel Easy Attendance provides a flexible attendance system that can work with your existing `User` model or its built-in Employee model.

It supports both **ZKTeco Pull mode and Push/ADMS mode**, making it suitable for offices, schools, factories, corporate HR systems, ERP applications, and other employee attendance environments.

Extracted and redesigned from a production HR system's attendance module, and validated end-to-end against real ZKTeco hardware (see [Tested against real devices](#tested-against-real-devices)).

## Why Laravel Easy Attendance?

Laravel Easy Attendance is designed as a reusable attendance and HR package for Laravel applications. It covers the complete flow from raw check-in/check-out punches to daily attendance summaries, leave, overtime, special working days, and salary generation.

### Key features

- Employee check-in and check-out tracking
- Laravel attendance management with reusable Eloquent models and traits
- Biometric attendance integration with ZKTeco devices
- ZKTeco Pull mode over IP
- ZKTeco Push / ADMS synchronization
- Attendance correction requests and approval workflow
- Shift and employee schedule management
- Leave and holiday management
- Daily attendance summaries: present, late, absent, leave, holiday, and day off
- Overtime detection, approval, and salary calculation
- Special working-day pay
- Salary slip generation from attendance data
- Attendance and salary reports
- Laravel events for attendance, correction, leave, overtime, and device-sync workflows
- Configurable feature flags so you can enable only the modules you need
- Polymorphic attendance subjects for `User`, `Employee`, `Staff`, or another model
- CSV export for every report, CSV bulk-import for employees, printable payslip/attendance-sheet views, and ready-made notification content for the key events — all zero external dependencies

### Use cases

Laravel Easy Attendance can be used for:

- Employee attendance systems
- HR and payroll applications
- School and college staff attendance
- Office attendance management
- Factory and industrial workforce tracking
- Corporate ERP systems
- Biometric attendance systems
- ZKTeco attendance integrations
- Multi-purpose Laravel business applications

### Requirements

- PHP 8.2 or higher
- Laravel 11, 12, or 13
- MySQL, MariaDB, SQLite, or another supported Laravel database driver

## Contents

- [Why Laravel Easy Attendance?](#why-laravel-easy-attendance)
- [Install](#install)
- [Quick start](#quick-start)
- [Core concept: the subject model](#core-concept-the-subject-model)
- [How a punch is resolved](#how-a-punch-is-resolved)
- [Corrections](#corrections)
- [Events](#events)
- [ZKTeco Biometric Attendance & Device Sync](#zkteco-biometric-attendance--device-sync)
  - [Mode A — Pull](#mode-a--pull-server-connects-out-to-the-device)
  - [Mode B — Push/ADMS](#mode-b--push--adms-the-device-connects-to-you)
  - [Viewing synced data](#viewing-synced-data)
- [HR & Payroll Core: Shifts, Leave, Holidays, Attendance Summaries, Overtime & Salary](#hr--payroll-core-shifts-leave-holidays-attendance-summaries-overtime--salary)
  - [Full worked example](#full-worked-example)
  - [Overtime + special working day, worked example](#overtime--special-working-day-worked-example)
- [Exports, bulk import, notifications & print views](#exports-bulk-import-notifications--print-views)
- [Configuration reference](#configuration-reference)
- [Routes reference](#routes-reference)
- [Tested against real devices](#tested-against-real-devices)
- [Zero external dependencies](#zero-external-dependencies)
- [Testing](#testing)
- [Building a UI](#building-a-ui)
- [Roadmap](#roadmap)
- [Frequently Asked Questions](#frequently-asked-questions)
- [Keywords](#keywords)

## Install

```bash
composer require easybdit/laraveleasyattendance
php artisan attendance:install
```

`attendance:install` publishes `config/attendance.php` and offers to run migrations. Everything works with zero config after that — the subject model defaults to your app's auth user model.

## Quick start

Add the trait to whichever model represents "who checks in" (defaults to your auth user model):

```php
use Easybdit\LaravelEasyAttendance\Traits\HasAttendance;

class User extends Authenticatable
{
    use HasAttendance;
}
```

```php
$user->checkIn();
$user->checkOut();
$user->attendanceOn('2026-09-07'); // ['first_in' => Carbon, 'last_out' => Carbon]
$user->attendances;                // MorphMany<Attendance>
```

Or via the built-in routes (auth-protected, prefixed `/attendance`):

```
POST /attendance/check-in
POST /attendance/check-out
GET  /attendance/today
```

## Core concept: the subject model

Every other attendance package on Packagist hardcodes `employee_id`. This one doesn't — `attendances.subject_id` + `subject_type` is a **polymorphic** relation (`morphs('subject')`), so a punch can belong to `User`, `Employee`, `Staff`, or anything else you point it at:

```php
// .env
ATTENDANCE_SUBJECT_MODEL=App\Models\Employee
```

Add `HasAttendance` to that model instead of `User`. Nothing else in the package needs to change — controllers, events, and device sync all resolve the subject model from config, never a hardcoded class.

## How a punch is resolved

`attendances` is a **punch log**, not a one-row-per-day table — every check-in and check-out is its own row (`type`: `check_in`/`check_out`, `source`: `manual`/`device`/`correction`). A device logs every raw punch of the day (in, out, in, out, ...), not a single clean pair, and a manual correction shouldn't just sit *beside* a bad device punch — it needs to actually win.

`Attendance::resolveDayWindow($punches)` (used by `attendanceOn()` and available for your own reports) handles this: within a day's punches, **manual entries take priority over device/api entries** in each direction — first-in is the earliest manual check-in if one exists, otherwise the earliest check-in of any source; last-out mirrors that for check-out. This is what makes an approved correction actually override a stray device punch instead of just adding a second, conflicting record.

## Corrections

```php
$correction = $user->requestAttendanceCorrection([
    'date' => '2026-09-07',
    'requested_in' => '09:05',
    'requested_out' => '18:10',
    'reason' => 'forgot to punch',
]);

$correction->approve($reviewerId, 'looks fine'); // or ->reject(...)
```

Approving doesn't just flip a status flag — it **creates real `check_in`/`check_out` punches** (`source: 'correction'`) from the requested times, so the correction flows through the exact same `resolveDayWindow()` logic as any other punch instead of living as a separate "note" your reports have to special-case.

Corrections are optional (`config('attendance.features.corrections')`) and routed at `/attendance/corrections`; approve/reject sit behind a separate `review_middleware` you can point at your own admin gate.

## Events

| Event | Fired when |
|---|---|
| `AttendanceRecorded` | Any punch is created — manual, device, or correction-approved |
| `AttendanceCorrectionRequested` | A subject submits a correction |
| `AttendanceCorrectionReviewed` | A correction is approved or rejected (`$correction->status` tells which) |
| `AttendanceDeviceSyncFailed` | A device fails to sync, escalating (Nth failure, then every Mth after — see config) |
| `LeaveRequested` | An employee submits a leave request |
| `LeaveReviewed` | A leave request is approved or rejected (`$leave->status` tells which) |
| `OvertimeReviewed` | An overtime record is approved or rejected — only `approved` reaches a salary slip |
| `AttendanceMarkedLate` | A day's summary (re)builds as `late` and wasn't already — a rebuild of an already-late day doesn't re-fire this |

The package has no opinion on notifications — listen for these and send however your app already does.

## ZKTeco Biometric Attendance & Device Sync

The one rule that matters for both modes below: **nothing shows up in `attendances` until a sync actually runs.** Adding a device just registers it — it does not fetch anything by itself. Pull mode fetches only when you call `pull` (or the scheduled command runs); push mode only stores data once the physical device actually calls your server. Always: **connect/register → sync → then query the data** — never the other way round.

### 0. Turn the feature on (once)

```
ATTENDANCE_FEATURE_DEVICE_SYNC=true
```
```bash
php artisan migrate
```
This adds the `attendance_devices` table and `device_id`/`device_user_id` columns on `attendances` (unique-constrained, so the same physical punch can never be imported twice even if you sync it twice).

Your subject model (`User`, `Employee`, ...) also needs a **PIN column** — this is what matches an incoming punch to a person. Add it yourself, e.g.:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('device_user_id')->nullable()->unique();
});
```

Then set each person's device PIN (`$user->device_user_id = '1001'`). A punch whose PIN matches nobody is **skipped and reported back**, not silently dropped — you'll see it in the pull response's `unmatched` list, or in the log on a push.

### Mode A — Pull (server connects out to the device)

Use this when your server can reach the device's IP directly (same network / VPN). No extra composer package needed — the ZK protocol client ships built into this package (see [Zero external dependencies](#zero-external-dependencies)); just `ext-sockets`, which PHP almost always has enabled already.

1. **Register the device:**
   ```php
   $device = AttendanceDevice::create([
       'name' => 'Main Gate',
       'ip' => '192.168.1.50',
       'port' => 4370,
       'status' => 'active',
   ]);
   ```
   or `POST /attendance/devices` with the same fields. Nothing is fetched yet at this point.
2. **(optional) Test the connection first**, before pulling any data — confirms the device is reachable without importing anything:
   ```
   POST /attendance/devices/{id}/test
   ```
3. **Pull — this is the step that actually fetches and stores the data:**
   ```
   POST /attendance/devices/{id}/pull
   ```
   or on a schedule so backlogs stay small:
   ```php
   // routes/console.php
   Schedule::command('attendance:sync-devices')->everyFiveMinutes();
   ```
   The response tells you exactly what happened: `{"success": true, "message": "134 logs fetched · 12 new · 2 PIN(s) not matched...", "imported": 12, "unmatched": [...]}`.
4. **Now query the data** (see [Viewing synced data](#viewing-synced-data) below) — before this step there is nothing to see for this device.

### Mode B — Push / ADMS (the device connects to you)

Use this when your server *can't* reach the device directly (remote site, no static IP, no VPN) — the device dials home to you instead. No extra composer package needed.

1. **Register the device with its serial number** (found on the device itself / its admin menu):
   ```php
   AttendanceDevice::create(['name' => 'Branch Office', 'serial_number' => 'ABCD1234', 'status' => 'active']);
   ```
2. **Point the device at your server:** on the device, Menu → Comm → Cloud Server Setting → Server Mode `ADMS`, Server Address = your app's domain, Enable = on.
3. **Wait for the device to call in.** It hits these fixed paths itself, on its own schedule (typically every 30s–a few minutes) — nothing to trigger from your side:
   ```
   GET|POST /iclock/cdata        (handshake, then the actual punch data)
   GET      /iclock/getrequest   (heartbeat / command poll)
   ```
4. **Check `is_online`/`last_seen_at`** on the device to confirm it has connected — that tells you the *connection* is live, before you check for data:
   ```php
   $device->fresh()->is_online;      // true once it's called in within the last 90s
   $device->fresh()->last_synced_at; // set the first time it actually sends punch data
   ```
5. **Now query the data** — populated automatically as the device pushes, no action needed on your end once step 2 is configured correctly.

### Viewing synced data

Regardless of which mode filled it in, synced punches are ordinary `Attendance` rows (`source: 'device'`) on the matched subject — query them the same way as manual punches:

```php
$user->attendances()->where('source', 'device')->get();   // raw punch log
$user->attendanceOn('2026-09-07');                          // resolved first-in/last-out for a day
$device->attendances()->latest('time')->first();            // most recent punch from a specific device
```

Both sync modes funnel through one shared `AttendanceDeviceSyncService::ingestLogs()`, so a punch is handled identically no matter which direction it arrived from — same matching, same dedup, same `AttendanceRecorded` event.

## HR & Payroll Core: Shifts, Leave, Holidays, Attendance Summaries, Overtime & Salary

Everything above works against *any* subject model with just a punch log. This layer is different — it's built around the package's own **`Employee`** model (salary, allowances, a device PIN) because computing "present vs. late vs. absent" and generating a payslip genuinely needs real employee data, not an arbitrary model. Off by default; turn the whole stack on with one var:

```
ATTENDANCE_FEATURE_HR_CORE=true
```
```bash
php artisan migrate
```
(Each piece — `employees`, `shifts`, `holidays`, `leave`, `summaries`, `salary`, `overtime`, `special_working_days` — is also an individually toggleable `ATTENDANCE_FEATURE_*` flag, in case you only want some of them.)

Every class below is under `Easybdit\LaravelEasyAttendance\`:
```php
use Easybdit\LaravelEasyAttendance\Models\{Employee, Shift, EmployeeShift, Holiday, LeaveType, Leave, OvertimeRecord, SpecialWorkingDay};
use Easybdit\LaravelEasyAttendance\Services\{AttendanceSummaryService, SalaryService};
```

**The pieces, in the order you'll normally set them up:**

1. **Employee** — the subject everything else attaches to.
   ```php
   $employee = Employee::create([
       'employee_code' => 'E-100', 'name' => 'Nusrat Jahan',
       'device_user_id' => '9001', // matches device sync's pin_column
       'basic_salary' => 30000, 'allowances' => ['house_rent' => 5000, 'medical' => 1000],
       'status' => 'active',
   ]);
   ```
   `Employee` itself uses `HasAttendance`, so `$employee->checkIn()`, `->checkOut()`, device sync — everything from the sections above — works on it directly. Also manageable over HTTP: `GET/POST /attendance/employees`, `GET/PUT/DELETE /attendance/employees/{id}`.

2. **Shift** — working hours + late grace + off days.
   ```php
   $shift = Shift::create(['name' => 'General', 'start_time' => '09:00', 'end_time' => '17:00', 'late_grace_minutes' => 10, 'off_days' => ['Friday']]);
   ```
   Or `GET/POST /attendance/shifts`, `PUT/DELETE /attendance/shifts/{id}`.

3. **Schedule** (`EmployeeShift`) — assign a shift to an employee for a date range (open-ended `end_date` = still current):
   ```php
   EmployeeShift::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'start_date' => '2026-08-01']);
   ```
   No assignment covering a date? `ShiftResolver` falls back to `config('attendance.default_shift')` — summaries work from day one, before you've set up a single shift. Or `GET/POST /attendance/employees/{id}/schedule`.

4. **Holiday** — a date nobody's expected to work, with no punch needed to explain the day.
   ```php
   Holiday::create(['name' => 'Independence Day', 'date' => '2026-03-26', 'is_recurring_yearly' => true]);
   ```

5. **Leave** — request → approve/reject, same pattern as attendance corrections:
   ```php
   $leave = $employee->requestLeave(['start_date' => '2026-09-03', 'end_date' => '2026-09-03', 'reason' => 'personal']);
   $leave->approve($reviewerId); // or ->reject(...)
   ```
   An approved leave outranks everything else for that date — even a stray punch. Or `GET/POST /attendance/employees/{id}/leaves`, `POST /attendance/leaves/{id}/approve|reject`.

6. **Attendance summary** — the actual present/late/absent/leave/holiday/day_off computation, one row per employee per day:
   ```bash
   php artisan attendance:build-summaries 2026-09-07   # one date, every active employee
   ```
   ```php
   (new AttendanceSummaryService)->buildOne($employee, '2026-09-07');
   (new AttendanceSummaryService)->buildForMonth($employee, 2026, 9);
   ```
   Priority order per day: **leave → holiday → day off → absent (no punch) → late/present** (from the shift-vs-first-punch comparison). Run `attendance:build-summaries` after every device sync (or schedule it) so summaries stay current.

7. **Overtime** — auto-detected from each day's summary (`ot_minutes`: last-out past the shift's end time), but lands as a **`pending`** `OvertimeRecord` — it only reaches a payslip once approved, so a punch-clock quirk can't quietly inflate pay:
   ```php
   $ot = OvertimeRecord::where('employee_id', $employee->id)->where('date', '2026-10-08')->first();
   $ot->approve($reviewerId, 'confirmed with supervisor'); // or ->reject(...)
   ```
   Rate follows the BD Labour Act convention this package was first built under — `hourly_rate = basic_salary / (salary_divisor × 8)`, OT pays `rate_multiplier ×` that, capped at `max_hours_per_day` (`config('attendance.overtime')`, all adjustable). Rebuilding a summary never reopens a record someone already approved/rejected — only an untouched auto/pending row gets updated.

8. **Special working days** — an employee specifically asked to work a day that's normally off (their shift's off day, or a company `Holiday`) gets *extra* pay for it, on top of ordinary salary, instead of that day just quietly counting as a plain "present":
   ```php
   SpecialWorkingDay::create(['employee_id' => $employee->id, 'date' => '2026-10-09', 'is_payable' => true]);
   ```
   `type` (`day_off` / `holiday` / `other`) is auto-detected from the date itself against that employee's own shift — not chosen by hand, so it can't drift out of sync. Payment defaults to a plain day's rate (`config('attendance.special_working_days')` — `daily_rate` / `fixed_amount` / `multiplier`, per type), or set a custom `payment_amount` on the record to override it. **Only pays if the employee actually punched in that day** — marking a date special doesn't create attendance, it just adds pay to attendance that already happened.

9. **Salary** — generated *from* that month's summaries (rebuilds them first, so a slip always reflects the latest synced attendance) — includes approved overtime and payable special-working-day pay automatically:
   ```bash
   php artisan attendance:generate-salary 2026 9              # every active employee
   php artisan attendance:generate-salary 2026 9 --employee=5 # just one
   ```
   Default rule (override by reading `SalaryService` — this is a starting point, not a full payroll engine): each absent day docks one `basic_salary / working_days_per_month`; **every Nth late day docks one more — the common "3 late = 1 absent" office policy** (`config('attendance.salary.late_deduction_ratio')`, default `3`). `net_salary = basic_salary + allowances - deductions + overtime_amount + special_pay_amount`, snapshotted onto the `SalarySlip` so a later raise never reshapes an already-generated one.

10. **Reports** — read-only JSON over the summary/salary tables (presentation is up to your own app/GUI):
   ```
   GET /attendance/reports/daily?date=2026-09-07
   GET /attendance/reports/monthly?year=2026&month=9
   GET /attendance/reports/employee/{employee}?from=2026-09-01&to=2026-09-07
   GET /attendance/reports/salary?year=2026&month=9
   ```

### Full worked example

All eight pieces together, one employee, one week — this is a real `tinker` run, output included, so you can see exactly what each step produces. (Overtime + special working days are a second, separate run below, on their own employee/month, so the numbers stay easy to follow.)

```php
$employee = Employee::create([
    'employee_code' => 'E-100', 'name' => 'Nusrat Jahan', 'device_user_id' => '9001',
    'basic_salary' => 30000, 'allowances' => ['house_rent' => 5000, 'medical' => 1000],
    'status' => 'active',
]);

$shift = Shift::create(['name' => 'General', 'start_time' => '09:00', 'end_time' => '17:00', 'late_grace_minutes' => 10, 'off_days' => ['Friday']]);
EmployeeShift::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'start_date' => '2026-08-01']);

Holiday::create(['name' => 'Independence Day', 'date' => '2026-09-05']);

$leave = $employee->requestLeave(['start_date' => '2026-09-03', 'end_date' => '2026-09-03', 'reason' => 'personal']);
$leave->approve();

// A normal week: present, a late day, on-leave, present, a holiday, present — Sunday's a
// working day here (only Friday is off) — then the 7th is skipped entirely (genuinely absent).
$employee->checkIn(['time' => '2026-09-01 09:05:00']); $employee->checkOut(['time' => '2026-09-01 17:10:00']);
$employee->checkIn(['time' => '2026-09-02 09:45:00']); $employee->checkOut(['time' => '2026-09-02 17:00:00']);
$employee->checkIn(['time' => '2026-09-04 08:55:00']); $employee->checkOut(['time' => '2026-09-04 17:05:00']);
$employee->checkIn(['time' => '2026-09-06 09:00:00']); $employee->checkOut(['time' => '2026-09-06 17:00:00']);

foreach (['2026-09-01','2026-09-02','2026-09-03','2026-09-04','2026-09-05','2026-09-06','2026-09-07'] as $d) {
    echo $d.': '.(new AttendanceSummaryService)->buildOne($employee, $d)->status.PHP_EOL;
}
```
```
2026-09-01: present
2026-09-02: late        (checked in 09:45, 45 min past the 09:00 shift start)
2026-09-03: leave       (the approved leave — outranks everything)
2026-09-04: present
2026-09-05: holiday     (no punch needed — Holiday already explains the day)
2026-09-06: present
2026-09-07: absent      (no punch, not a holiday/leave/off-day)
```
```php
$slip = (new SalaryService)->generate($employee, 2026, 9);
// present=4  absent=21  late=1  leave=1
// basic=30000.00  deduction=21000.00  net=15000.00
// (21 absent days × 30000/30 = 21000 deduction — buildForMonth() filled in every
// unpunched day of September as absent/day_off, not just the 7 days above)
```

### Overtime + special working day, worked example

Same shift setup, a different employee, basic 26000 (per-day rate = 26000/30 ≈ 866.67):

```php
// 3 late days in October — the late-ratio deduction (config default: every 3rd = 1 absent) kicks in.
foreach (['2026-10-05', '2026-10-06', '2026-10-07'] as $d) {
    $employee->checkIn(['time' => $d.' 09:45:00']);
    $employee->checkOut(['time' => $d.' 17:00:00']);
}

// Worked till 19:30 on the 8th — 2.5h past the 17:00 shift end.
$employee->checkIn(['time' => '2026-10-08 09:00:00']);
$employee->checkOut(['time' => '2026-10-08 19:30:00']);

// Asked to work Friday the 9th (the shift's off_day) — and did.
$employee->checkIn(['time' => '2026-10-09 09:00:00']);
$employee->checkOut(['time' => '2026-10-09 17:00:00']);
$special = SpecialWorkingDay::create(['employee_id' => $employee->id, 'date' => '2026-10-09', 'is_payable' => true]);
// $special->type === 'day_off' — auto-detected

foreach (['2026-10-05','2026-10-06','2026-10-07','2026-10-08','2026-10-09'] as $d) {
    (new AttendanceSummaryService)->buildOne($employee, $d);
}

$ot = OvertimeRecord::where('employee_id', $employee->id)->where('date', '2026-10-08')->first();
// status=pending  ot_hours=2.00 (capped from 2.5)  ot_rate=250.0000  ot_amount=500.00
$ot->approve();

$slip = (new SalaryService)->generate($employee, 2026, 10);
```
```
present=5  absent=22  late=3
basic=26000.00  deduction=19933.33   (23 days × 866.67 — 22 absent + 1 from 3 late ÷ 3)
overtime_hours=2.00  overtime_amount=500.00
special_pay_amount=866.67            (one day's rate, for showing up on an off day)
net_salary=7433.34                   (26000 − 19933.33 + 500 + 866.67)
```

## Exports, bulk import, notifications & print views

Everything here is zero-dependency, same as the rest of the package — no maatwebsite/excel, no dompdf.

### CSV export

Add `&format=csv` to any of the four report endpoints for a downloadable file instead of JSON:

```
GET /attendance/reports/daily?date=2026-11-01&format=csv
GET /attendance/reports/monthly?year=2026&month=11&format=csv
GET /attendance/reports/employee/{employee}?from=...&to=...&format=csv
GET /attendance/reports/salary?year=2026&month=11&format=csv
```

Plain `fputcsv()` streamed to the response (`Http\Controllers\Concerns\ExportsCsv`) — opens directly in Excel/Sheets.

### CSV import (bulk-add employees)

```
POST /attendance/employees/import   (multipart, field name "file")
```

Header row: `employee_code, name, email, phone, designation, device_user_id, basic_salary, joined_at, status` (any order; unrecognized columns are ignored) — plus any `allowance_*` column (e.g. `allowance_house_rent`) becomes a key in that employee's `allowances` map. A bad row is skipped and reported, not fatal to the rest of the file:

```json
{"imported": 48, "skipped": 2, "errors": [{"row": 5, "message": "The employee code has already been taken."}]}
```

Use `EmployeeCsvImporter` directly if you'd rather trigger this from an Artisan command or a job than the HTTP endpoint.

### Notifications

The package fires events (see [Events](#events)) but has no opinion on *who* to notify — that's your app's call. What it does provide: ready-made **content** for each notifiable event, using only `illuminate/notifications` (core Laravel, no new package) over the `mail` and `database` channels:

| Notification | For event |
|---|---|
| `AttendanceMarkedLateNotification` | `AttendanceMarkedLate` |
| `LeaveRequestedNotification` | `LeaveRequested` |
| `LeaveReviewedNotification` | `LeaveReviewed` |
| `OvertimeReviewedNotification` | `OvertimeReviewed` |
| `AttendanceDeviceSyncFailedNotification` | `AttendanceDeviceSyncFailed` |

Wire one up in your own `EventServiceProvider` (or anywhere — they're plain `Illuminate\Notifications\Notification` classes):

```php
use Easybdit\LaravelEasyAttendance\Events\AttendanceMarkedLate;
use Easybdit\LaravelEasyAttendance\Notifications\AttendanceMarkedLateNotification;

Event::listen(AttendanceMarkedLate::class, function ($event) {
    $hrUsers = User::where('role', 'hr')->get();
    Notification::send($hrUsers, new AttendanceMarkedLateNotification($event->employee, $event->date, $event->lateMinutes));
});
```

The `database` channel needs the standard Laravel `notifications` table — `php artisan notifications:table && php artisan migrate` if you haven't already got one.

### Print views (payslip & attendance sheet)

```
GET /attendance/salary/{slip}/print
GET /attendance/reports/monthly/print?year=2026&month=11
```

Plain HTML with a "Print / Save as PDF" button that calls the browser's own print dialog — every modern browser saves that straight to PDF, no server-side PDF library involved. Views are published (`--tag=attendance-views`, landing in `resources/views/vendor/attendance/`) so you can restyle or rebrand them freely.

## Configuration reference

`config/attendance.php`, after `php artisan vendor:publish --tag=attendance-config`:

| Key | Default | Purpose |
|---|---|---|
| `subject_model` | your auth user model | The model attendance belongs to |
| `routes.enabled` | `true` | Turn off the built-in HTTP routes entirely |
| `routes.prefix` | `attendance` | URL prefix for all routes |
| `routes.middleware` | `['web','auth']` | Applied to every route below the prefix |
| `routes.review_middleware` | `['web','auth']` | Extra gate on correction/device management routes — point at your own admin `can:` |
| `features.corrections` | `true` | Correction request/approve/reject |
| `features.device_sync` | `false` | ZKTeco pull + push/ADMS |
| `features.employees` / `shifts` / `holidays` / `leave` / `summaries` / `salary` / `overtime` / `special_working_days` | `false` | HR core, each individually toggleable — or set `ATTENDANCE_FEATURE_HR_CORE=true` to flip all eight at once |
| `device_sync.pin_column` | `device_user_id` | Column on the subject model's table holding the device PIN |
| `device_sync.online_threshold_seconds` | `90` | How recently a push device must have been seen to count "online" |
| `device_sync.notify_after_failures` / `notify_every` | `2` / `5` | `AttendanceDeviceSyncFailed` escalation schedule |
| `default_shift` | 09:00–18:00, 15min grace, Friday off | Fallback used by `ShiftResolver` when no roster entry covers a date |
| `salary.working_days_per_month` | `30` | Divides `basic_salary` into a per-day rate for deductions |
| `salary.late_deduction_ratio` | `3` | Every Nth late day docks one more day's pay |
| `salary.deduct_for_absent` / `deduct_for_late` | `true` / `true` | Turn either deduction rule off |
| `overtime.salary_divisor` / `rate_multiplier` | `26` / `2` | OT hourly rate = `basic_salary / (divisor × 8)`, paid at `multiplier ×` that |
| `overtime.max_hours_per_day` | `2` | Caps auto-detected OT per day, however late the last punch |
| `overtime.auto_detect` | `true` | Auto-create a pending `OvertimeRecord` whenever a summary has `ot_minutes` |
| `special_working_days.day_off_payment_type` / `holiday_payment_type` | `daily_rate` | `daily_rate` \| `fixed_amount` \| `multiplier`, per special-day type |
| `special_working_days.*_fixed_amount` / `*_multiplier` | `1000` / `1.0` | Used when the payment type above is `fixed_amount` / `multiplier` |

## Routes reference

| Method | URI | Feature |
|---|---|---|
| POST | `/attendance/check-in` | core |
| POST | `/attendance/check-out` | core |
| GET | `/attendance/today` | core |
| GET/POST | `/attendance/corrections` | `corrections` |
| POST | `/attendance/corrections/{id}/approve\|reject` | `corrections` (behind `review_middleware`) |
| GET/POST/PUT/DELETE | `/attendance/devices...` | `device_sync` (behind `review_middleware`) |
| GET/POST | `/iclock/cdata`, `/iclock/getrequest`, `/iclock/devicecmd` | `device_sync` — public, no prefix, fixed paths (device firmware calls these directly) |
| GET | `/attendance/reports/daily\|monthly\|employee/{id}\|salary` | `summaries` (`salary` route also needs `features.salary`), behind `review_middleware` |
| GET/POST/PUT/DELETE | `/attendance/employees...` | `employees`, behind `review_middleware` |
| GET/POST | `/attendance/employees/{id}/schedule` | `employees` + `shifts`, behind `review_middleware` |
| GET/POST/PUT/DELETE | `/attendance/shifts...` | `shifts`, behind `review_middleware` |
| GET/POST | `/attendance/employees/{id}/leaves` | `employees` + `leave`, behind `review_middleware` |
| POST | `/attendance/leaves/{id}/approve\|reject` | `leave`, behind `review_middleware` |
| GET/POST/PUT/DELETE | `/attendance/holidays...` | `holidays`, behind `review_middleware` |
| GET/POST/PUT/DELETE | `/attendance/leave-types...` | `leave`, behind `review_middleware` |
| GET | `/attendance/employees/{id}/overtime` | `employees` + `overtime`, behind `review_middleware` |
| POST | `/attendance/overtime/{id}/approve\|reject` | `overtime`, behind `review_middleware` |
| GET/POST | `/attendance/employees/{id}/special-working-days` | `employees` + `special_working_days`, behind `review_middleware` |
| PUT/DELETE | `/attendance/special-working-days/{id}` | `special_working_days`, behind `review_middleware` |
| POST | `/attendance/employees/import` | `employees`, behind `review_middleware` (CSV bulk-add) |
| GET | `/attendance/reports/monthly/print` | `summaries`, behind `review_middleware` (printable attendance sheet) |
| GET | `/attendance/salary/{id}/print` | `salary`, behind `review_middleware` (printable payslip) |

All the employee/shift/leave/overtime/special-working-day routes above take an explicit `{employee}` — they're HR/admin management endpoints, not "my own" self-service, since `Employee` is a separate concept from whatever `attendance.subject_model` your `Auth::user()` actually is (see [Core concept: the subject model](#core-concept-the-subject-model)). There's deliberately no `store()` for overtime — records are only ever auto-detected (see `OvertimeRecord::detectFromSummary()`), never created by hand over HTTP.

## Tested against real devices

Not just unit-tested against fixtures — validated end to end against actual production ZKTeco hardware, following the exact register → connect/sync → query sequence documented above:

- **Push (ADMS):** simulated a real device's ATTLOG push (its genuine serial number) against `/iclock/cdata` — handshake accepted, punch ingested, heartbeat updated.
- **Pull (IP), connection only:** registered a live device, tested the connection — succeeded, confirmed **zero** attendance rows existed for it beforehand (nothing is fetched just by registering/testing).
- **Pull (IP), full sync:** same device, `pull` — **7,136 real attendance logs fetched in ~13 seconds**. PINs with no matching subject were correctly reported (not silently dropped) and produced no rows.
- **Pull (IP), matched subject:** assigned a subject a real PIN seen in that log, pulled again — **23 real historical punches** (spanning roughly 4 months of real dates) landed on that subject and were immediately queryable via `$user->attendances()` and `$user->attendanceOn($date)`.
- **Pull (IP), built-in ZK client:** re-ran the same live-device pull after replacing the external ZK library with this package's own vendored client (see below) — **7,172 real logs fetched in ~7 seconds**, identical behavior, zero external package involved.

## Zero external dependencies

This package requires only Laravel itself (`illuminate/support`, `illuminate/database`) and the `ext-sockets` PHP extension (near-universally enabled already) — nothing else, for either sync mode:

- **Push/ADMS** never needed anything extra — it's plain HTTP, handled by `AdmsPushController`.
- **Pull** talks the ZKTeco UDP protocol directly via `Support\Zk\ZkClient`, a from-scratch-in-this-package client trimmed to exactly what device sync needs (connect, fetch attendance logs, fetch enrolled users, set the push comm key). It started as a wrapper around [coding-libs/zkteco-php](https://github.com/coding-libs/zkteco-php); the wire-protocol logic (packet framing, checksum, record parsing) is ported from it under MIT license — see [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md) — unchanged on purpose, since it's what's validated against real hardware above, but no longer an installable dependency: one less thing that can go missing or version-conflict in your project.

## Testing

```bash
composer install
composer test
```

Runs against sqlite in-memory by default (Orchestra Testbench) — no service container to stand up locally. Override via real env vars, no file to edit, for any other driver — this is what CI itself uses (MySQL 8, via a service container in `.github/workflows/tests.yml`):

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=your_test_db DB_USERNAME=... DB_PASSWORD=... composer test
```

57 tests / 169 assertions cover: punch resolution priority (manual over device), correction approve/reject creating real punches, every event, device push matching/unmatched-PIN/idempotency, pull-mode failure escalation, the check-in/correction HTTP routes, the HR core — summary status priority (leave > holiday > day off > absent > late/present), late-minute math, recurring-yearly holidays, an approved leave overriding a stray punch, overtime/special-working-day pay (the late-ratio deduction boundary, OT capped-and-approval-gated, special-day type auto-detection, pay withheld unless the employee showed up), every management HTTP route (employee/shift/schedule/leave/holiday/leave-type/overtime/special-working-day) — and CSV export/import, notification content, and both print views. Runs on GitHub Actions against MySQL 8 on PHP 8.2/8.3/8.4 on every push.

**A cross-database gotcha this suite caught:** every `date`-cast column (`Holiday::date`, `Leave::start_date/end_date`, `EmployeeShift::start_date/end_date`, `AttendanceSummary::date`, `OvertimeRecord::date`) gets written by Eloquent through the connection's full datetime format (e.g. `"2026-09-01 00:00:00"`), not a bare date. MySQL's `DATE` columns silently truncate that back down on insert; SQLite stores it verbatim, so an exact-string `where('date', ...)` only ever matches on MySQL. Every such comparison in this codebase uses `whereDate()` instead, which compares just the date part at the SQL level regardless of which of those actually got stored — worth knowing if you query these columns yourself.

## Building a UI

This package is headless by design (JSON + two print views) — no bundled Vue/React/Livewire UI, and no plan to maintain three separate framework-specific packages. **[docs/frontend-examples.md](docs/frontend-examples.md)** has real, copy-adjustable code for the same check-in/check-out widget and report table built four ways: **Vue 3**, **React**, **Livewire** (calls the package's PHP directly — no HTTP round-trip, no build step), and plain Blade + vanilla JS — plus a quick "which one should I pick" guide and how CSRF/auth works for each.

## Roadmap

- Maintain releases and improve compatibility across supported Laravel versions.
- A pluggable `ShiftResolver`/leave/holiday *contract* for teams who want summaries against their own existing shift/roster system instead of this package's `Shift`/`EmployeeShift`.

## Frequently Asked Questions

### Is this a Laravel attendance package?

Yes. Laravel Easy Attendance provides employee check-in/check-out, attendance logs, daily summaries, corrections, shifts, leave, overtime, salary generation, and attendance reports for Laravel applications.

### Does it support ZKTeco biometric devices?

Yes. The package supports both ZKTeco Pull mode and ZKTeco Push / ADMS mode. Pull mode connects from the Laravel server to the device, while Push / ADMS allows the device to send attendance data to the Laravel application.

### Do I need to install any other package for ZKTeco support?

No. Both sync modes work with zero external packages — the ZK protocol client is built directly into this package (see [Zero external dependencies](#zero-external-dependencies)). All you need is `ext-sockets`, a standard PHP extension almost every install already has enabled.

### Can I use my existing User model?

Yes. The attendance system uses a polymorphic `subject` relation, so you can attach attendance to your existing `User`, `Employee`, `Staff`, or another model.

### Does it include HR and payroll features?

The optional HR core includes employees, shifts, schedules, holidays, leave, attendance summaries, overtime, special working-day pay, and salary-slip generation.

### Which Laravel versions are supported?

The package is designed for Laravel 11, Laravel 12, and Laravel 13 and requires PHP 8.2 or higher.

## Keywords

Laravel attendance, Laravel attendance package, Laravel attendance management, employee attendance, employee attendance system, biometric attendance, ZKTeco attendance, ZKTeco Laravel integration, ZKTeco ADMS, biometric attendance system, attendance management system, HR management, leave management, shift management, overtime management, payroll, salary management, attendance reports, Laravel HR package.

## License

MIT.
