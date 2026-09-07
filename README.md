# LaravelEasyAttendance

Drop-in attendance tracking for any Laravel app — check-in/out, correction requests, and ZKTeco biometric device sync (pull *and* push/ADMS) work against your existing `User` model out of the box, or any model you choose. Optionally, a full HR core layered on top: employees, shifts, schedules, holidays, leave, present/late/absent/holiday summaries, and salary generation.

Extracted and redesigned from a production HR system's attendance module, and validated end-to-end against real ZKTeco hardware (see [Tested against real devices](#tested-against-real-devices)).

## Contents

- [Install](#install)
- [Quick start](#quick-start)
- [Core concept: the subject model](#core-concept-the-subject-model)
- [How a punch is resolved](#how-a-punch-is-resolved)
- [Corrections](#corrections)
- [Events](#events)
- [ZKTeco device sync](#zkteco-device-sync)
  - [Mode A — Pull](#mode-a--pull-server-connects-out-to-the-device)
  - [Mode B — Push/ADMS](#mode-b--push--adms-the-device-connects-to-you)
  - [Viewing synced data](#viewing-synced-data)
- [HR core: shifts, leave, holidays, summaries, salary](#hr-core-shifts-leave-holidays-summaries-salary)
  - [Full worked example](#full-worked-example)
- [Configuration reference](#configuration-reference)
- [Routes reference](#routes-reference)
- [Tested against real devices](#tested-against-real-devices)
- [Testing](#testing)
- [Roadmap](#roadmap)

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
| `AttendanceMarkedLate` | A day's summary (re)builds as `late` and wasn't already — a rebuild of an already-late day doesn't re-fire this |

The package has no opinion on notifications — listen for these and send however your app already does.

## ZKTeco device sync

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

Use this when your server can reach the device's IP directly (same network / VPN).

1. **Install the ZK client library** (only needed for pull):
   ```bash
   composer require coding-libs/zkteco-php
   ```
2. **Register the device:**
   ```php
   $device = AttendanceDevice::create([
       'name' => 'Main Gate',
       'ip' => '192.168.1.50',
       'port' => 4370,
       'status' => 'active',
   ]);
   ```
   or `POST /attendance/devices` with the same fields. Nothing is fetched yet at this point.
3. **(optional) Test the connection first**, before pulling any data — confirms the device is reachable without importing anything:
   ```
   POST /attendance/devices/{id}/test
   ```
4. **Pull — this is the step that actually fetches and stores the data:**
   ```
   POST /attendance/devices/{id}/pull
   ```
   or on a schedule so backlogs stay small:
   ```php
   // routes/console.php
   Schedule::command('attendance:sync-devices')->everyFiveMinutes();
   ```
   The response tells you exactly what happened: `{"success": true, "message": "134 logs fetched · 12 new · 2 PIN(s) not matched...", "imported": 12, "unmatched": [...]}`.
5. **Now query the data** (see [Viewing synced data](#viewing-synced-data) below) — before this step there is nothing to see for this device.

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

## HR core: shifts, leave, holidays, summaries, salary

Everything above works against *any* subject model with just a punch log. This layer is different — it's built around the package's own **`Employee`** model (salary, allowances, a device PIN) because computing "present vs. late vs. absent" and generating a payslip genuinely needs real employee data, not an arbitrary model. Off by default; turn the whole stack on with one var:

```
ATTENDANCE_FEATURE_HR_CORE=true
```
```bash
php artisan migrate
```
(Each piece — `employees`, `shifts`, `holidays`, `leave`, `summaries`, `salary` — is also an individually toggleable `ATTENDANCE_FEATURE_*` flag, in case you only want some of them.)

Every class below is under `Easybdit\LaravelEasyAttendance\`:
```php
use Easybdit\LaravelEasyAttendance\Models\{Employee, Shift, EmployeeShift, Holiday, LeaveType, Leave};
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
   `Employee` itself uses `HasAttendance`, so `$employee->checkIn()`, `->checkOut()`, device sync — everything from the sections above — works on it directly.

2. **Shift** — working hours + late grace + off days.
   ```php
   $shift = Shift::create(['name' => 'General', 'start_time' => '09:00', 'end_time' => '17:00', 'late_grace_minutes' => 10, 'off_days' => ['Friday']]);
   ```

3. **Schedule** (`EmployeeShift`) — assign a shift to an employee for a date range (open-ended `end_date` = still current):
   ```php
   EmployeeShift::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'start_date' => '2026-08-01']);
   ```
   No assignment covering a date? `ShiftResolver` falls back to `config('attendance.default_shift')` — summaries work from day one, before you've set up a single shift.

4. **Holiday** — a date nobody's expected to work, with no punch needed to explain the day.
   ```php
   Holiday::create(['name' => 'Independence Day', 'date' => '2026-03-26', 'is_recurring_yearly' => true]);
   ```

5. **Leave** — request → approve/reject, same pattern as attendance corrections:
   ```php
   $leave = $employee->requestLeave(['start_date' => '2026-09-03', 'end_date' => '2026-09-03', 'reason' => 'personal']);
   $leave->approve($reviewerId); // or ->reject(...)
   ```
   An approved leave outranks everything else for that date — even a stray punch.

6. **Attendance summary** — the actual present/late/absent/leave/holiday/day_off computation, one row per employee per day:
   ```bash
   php artisan attendance:build-summaries 2026-09-07   # one date, every active employee
   ```
   ```php
   (new AttendanceSummaryService)->buildOne($employee, '2026-09-07');
   (new AttendanceSummaryService)->buildForMonth($employee, 2026, 9);
   ```
   Priority order per day: **leave → holiday → day off → absent (no punch) → late/present** (from the shift-vs-first-punch comparison). Run `attendance:build-summaries` after every device sync (or schedule it) so summaries stay current.

7. **Salary** — generated *from* that month's summaries (rebuilds them first, so a slip always reflects the latest synced attendance):
   ```bash
   php artisan attendance:generate-salary 2026 9              # every active employee
   php artisan attendance:generate-salary 2026 9 --employee=5 # just one
   ```
   Default rule (override by reading `SalaryService` — this is a starting point, not a full payroll engine): each absent day docks one `basic_salary / working_days_per_month`; every Nth late day docks one more (`config('attendance.salary')`). `net_salary = basic_salary + allowances - deductions`, snapshotted onto the `SalarySlip` so a later raise never reshapes an already-generated one.

8. **Reports** — read-only JSON over the summary/salary tables (presentation is up to your own app/GUI):
   ```
   GET /attendance/reports/daily?date=2026-09-07
   GET /attendance/reports/monthly?year=2026&month=9
   GET /attendance/reports/employee/{employee}?from=2026-09-01&to=2026-09-07
   GET /attendance/reports/salary?year=2026&month=9
   ```

### Full worked example

All eight pieces together, one employee, one week — this is a real `tinker` run, output included, so you can see exactly what each step produces:

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
| `features.employees` / `shifts` / `holidays` / `leave` / `summaries` / `salary` | `false` | HR core, each individually toggleable — or set `ATTENDANCE_FEATURE_HR_CORE=true` to flip all six at once |
| `device_sync.pin_column` | `device_user_id` | Column on the subject model's table holding the device PIN |
| `device_sync.online_threshold_seconds` | `90` | How recently a push device must have been seen to count "online" |
| `device_sync.notify_after_failures` / `notify_every` | `2` / `5` | `AttendanceDeviceSyncFailed` escalation schedule |
| `default_shift` | 09:00–18:00, 15min grace, Friday off | Fallback used by `ShiftResolver` when no roster entry covers a date |
| `salary.working_days_per_month` | `30` | Divides `basic_salary` into a per-day rate for deductions |
| `salary.late_deduction_ratio` | `3` | Every Nth late day docks one more day's pay |
| `salary.deduct_for_absent` / `deduct_for_late` | `true` / `true` | Turn either deduction rule off |

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

`Employee`/`Shift`/`EmployeeShift`/`Holiday`/`LeaveType`/`Leave` have no bundled CRUD routes — they're plain Eloquent models; build whatever create/edit screens your own app/GUI needs directly against them (same as any other model in your app).

## Tested against real devices

Not just unit-tested against fixtures — validated end to end against actual production ZKTeco hardware, following the exact register → connect/sync → query sequence documented above:

- **Push (ADMS):** simulated a real device's ATTLOG push (its genuine serial number) against `/iclock/cdata` — handshake accepted, punch ingested, heartbeat updated.
- **Pull (IP), connection only:** registered a live device, tested the connection — succeeded, confirmed **zero** attendance rows existed for it beforehand (nothing is fetched just by registering/testing).
- **Pull (IP), full sync:** same device, `pull` — **7,136 real attendance logs fetched in ~13 seconds**. PINs with no matching subject were correctly reported (not silently dropped) and produced no rows.
- **Pull (IP), matched subject:** assigned a subject a real PIN seen in that log, pulled again — **23 real historical punches** (spanning roughly 4 months of real dates) landed on that subject and were immediately queryable via `$user->attendances()` and `$user->attendanceOn($date)`.

## Testing

```bash
composer install
composer test
```

Runs against sqlite in-memory by default (Orchestra Testbench). To run against another driver instead — e.g. this repo's own dev environment, which has no `pdo_sqlite` — set real env vars, no file to edit:

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=your_test_db DB_USERNAME=... DB_PASSWORD=... composer test
```

28 tests / 75 assertions cover: punch resolution priority (manual over device), correction approve/reject creating real punches, every event, device push matching/unmatched-PIN/idempotency, pull-mode failure escalation, the HTTP routes, and the full HR core — summary status priority (leave > holiday > day off > absent > late/present), late-minute math, recurring-yearly holidays, an approved leave overriding a stray punch, and salary deduction arithmetic.

## Roadmap

- Packagist publish.
- A pluggable `ShiftResolver`/leave/holiday *contract* for teams who want summaries against their own existing shift/roster system instead of this package's `Shift`/`EmployeeShift`.

## License

MIT.
