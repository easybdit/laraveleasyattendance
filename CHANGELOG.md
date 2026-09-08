# Changelog

All notable changes to this project are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/); this project follows [Semantic Versioning](https://semver.org/) as of `1.0.0` — versions before that (`0.x`) could and did include breaking changes in minor releases.

## [Unreleased]

## [2.0.0] - 2026-09-08

### Changed
- **BREAKING: every package table is now prefixed `easyattendance_`.** `employees`, `shifts`, `employee_shifts`, `holidays`, `leave_types`, `leaves`, `salary_slips`, `overtime_records`, `special_working_days`, `departments`, `designations` are exactly the kind of generic table names a host app (or another package) is likely to already have — this closes that collision risk for good, and brings the other four tables (`attendances`, `attendance_corrections`, `attendance_devices`, `attendance_summaries`, previously `attendance_`-prefixed only) in line with the same convention. Every table name is individually overridable via the new `config('attendance.table_names')` array — same shape as `spatie/laravel-permission`'s `table_names` config — see the README's new [Table names](README.md#table-names) section. Shipped as a major version, on the very first day of `v1.0.0`, specifically because the real-world cost of breaking this now (4 Packagist installs) is essentially zero compared to doing it later against real user data.
  - **Upgrading:** there is no automatic migration — this only matters if you've already run `php artisan migrate` on `v1.x`. Either start fresh (`php artisan migrate:fresh`, only safe if you don't need to keep existing data), or write your own migration renaming each old table to its new `easyattendance_*` name (see `config/attendance.php` for the full old-name → new-name map) before upgrading the package.
  - Every model now resolves its table via the new `Models\Concerns\HasPackageTable` trait instead of Eloquent's default naming convention; every migration's `Schema::create()`/`constrained()` calls read the same `table_names` config.
  - Validation rules that referenced a table by raw string (`unique:employees,...`, `exists:departments,id`) now use `Rule::unique(Employee::class, ...)`/`Rule::exists(Department::class, ...)` instead, so they keep resolving correctly regardless of what `table_names` is set to.
  - A few composite indexes/unique constraints (`employee_shifts`, `leaves`, `salary_slips`, `overtime_records`, `special_working_days`, and the two `attendances`/`attendance_corrections` subject indexes) now have an explicit short name instead of Laravel's auto-generated `{table}_{columns}_index` — the auto-generated name for `employee_shifts`' composite index exceeded MySQL's 64-character identifier limit once the longer prefix was applied, and an explicit name keeps every index safe regardless of how long a custom `table_names` override might be.
- Full suite (67 tests / 196 assertions) green against MySQL with the new prefixed tables.

## [1.0.0] - 2026-09-08

First stable release. Semantic Versioning applies from here on — see the policy note at the top of this file.

### Changed
- **BREAKING: List endpoints are now paginated.** `GET /attendance/employees`, `/shifts`, `/departments`, `/designations`, `/holidays`, `/leave-types`, and `/devices` now return a standard Laravel paginator object (`{data, current_page, last_page, per_page, total, ...}`) instead of a flat JSON array. Read items from `data`. Control page size with `?per_page=` (default 25, capped at 100) and page with `?page=`. This was a genuine production-readiness gap — an unbounded `->get()` on these indexes would eventually return every row in the table.
- **`GET /attendance/employees` gains filtering** — `?search=` (matches `name`, `employee_code`, or `email`), `?status=`, `?department_id=`.
- `HrCoreHttpExtraTest`'s holiday-index assertion updated for the new paginator shape (`assertJsonCount(1, 'data')`); full suite (67 tests / 196 assertions) still green.

### Added
- **Leave balance tracking** — `LeaveType::balanceForEmployee()` / `Employee::leaveBalances()` (allowed/used/remaining per type per calendar year, clamped at zero, only counting approved leaves), plus `GET /attendance/employees/{id}/leave-balance?year=`.
- **`Department` and `Designation` models** (`ATTENDANCE_FEATURE_DEPARTMENTS`) — alongside, not replacing, the existing plain `designation` string column on `Employee`. Deleting a `Department` nulls out any `Designation`/`Employee` pointing at it rather than blocking the delete. Full CRUD over HTTP (`DepartmentController`, `DesignationController`).
- **Localization** — every string the package generates itself (notification content, print-view labels, day-status labels) now goes through Laravel's translation system (`resources/lang/en/{attendance,notifications,print}.php`, `illuminate/translation` — core Laravel, no new dependency), publishable via `--tag=attendance-lang` for adding other locales.
- Test suite grew to 67 tests / 196 assertions covering all three.

### Fixed
- `AttendanceDeviceSyncFailedNotification`'s mail content concatenated the `:ip` and `:port` placeholders with no separator between them (`:ip:port`), which Laravel's translator replaces in a way that silently drops the boundary between the two values — caught while wiring up the localization pass. Now a single `:address` placeholder built in the notification class.

### Docs
- `docs/frontend-examples.md` — real, copy-adjustable code for a check-in/check-out widget and a report table built four ways (Vue 3, React, Livewire, plain Blade + vanilla JS), plus a CSRF/auth explainer and a "which one should I pick" guide. The package stays headless (JSON + two print views) by design — this documents *how to consume it*, not a bundled UI; no plan to maintain three separate framework-specific UI packages (see README's new "Building a UI" section).

See the README's [Roadmap](README.md#roadmap) for what's planned beyond 1.0.0.

## [0.4.0] - 2026-09-08

### Added
- **CSV export** — `&format=csv` on any of the four report endpoints (daily/monthly/employee-wise/salary), streamed via plain `fputcsv()` (`Http\Controllers\Concerns\ExportsCsv`). No maatwebsite/excel or PhpSpreadsheet.
- **CSV bulk-import for employees** — `POST /attendance/employees/import`, backed by `EmployeeCsvImporter` (plain `fgetcsv()`). A bad row is skipped and reported (`{row, message}`), not fatal to the batch; `allowance_*` columns map into the employee's `allowances` array.
- **Ready-made Notification classes** — `AttendanceMarkedLateNotification`, `LeaveRequestedNotification`, `LeaveReviewedNotification`, `OvertimeReviewedNotification`, `AttendanceDeviceSyncFailedNotification`, using only `illuminate/notifications` (mail + database channels). The package still fires events only and sends nothing itself — these are ready-made *content* to attach in your own listener, since only your app knows who should be told.
- **Printable payslip and attendance-sheet views** — `GET /attendance/salary/{id}/print`, `GET /attendance/reports/monthly/print`, plain HTML with a print-to-PDF button (browser-native, no dompdf/wkhtmltopdf). Publishable via `--tag=attendance-views`.
- Test suite grew to 57 tests / 169 assertions covering all four.

### Removed
- **`coding-libs/zkteco-php` is no longer a dependency (suggested or otherwise).** Pull-mode device sync now uses `Support\Zk\ZkClient`, a from-scratch-in-this-package ZK UDP protocol client trimmed to exactly what's needed (connect, fetch attendance logs, fetch enrolled users, set push comm key) — ported from that library under its MIT license (unchanged wire-protocol logic on purpose; see `THIRD-PARTY-NOTICES.md`). This package now has zero external composer dependencies beyond Laravel itself — just the `ext-sockets` PHP extension, now declared explicitly in `composer.json`. Re-verified end to end against the same live device used in the original hardware validation: 7,172 real logs fetched in ~7s, identical behavior.
- As a side effect, `ZKService`'s custom connect timeout is now actually honored — the old wrapper checked for a `timeout` property on the external library's client object that never existed, so a caller-supplied timeout was silently ignored in favor of that library's own default. `Support\Zk\ZkClient` takes the timeout as a real constructor argument.

### Changed
- `AttendanceDeviceSyncService::pull()`'s socket timeout is now configurable (`attendance.device_sync.pull_timeout_seconds`, default 15s, was hardcoded) rather than fixed.

### Added
- Management HTTP endpoints for `Employee`, `Shift`, schedule (`EmployeeShift`) assignment, `Leave`, `Holiday`, `LeaveType`, `Overtime` (approve/reject only — records are auto-detected, never created by hand), and `SpecialWorkingDay` — `EmployeeController`, `ShiftController`, `LeaveController`, `HolidayController`, `LeaveTypeController`, `OvertimeController`, `SpecialWorkingDayController`. All nested under an explicit `{employee}` where relevant (HR/admin actions, not "my own" self-service — `Employee` is a separate concept from whatever `attendance.subject_model` your `Auth::user()` is), behind `review_middleware`.
- `LICENSE` file (MIT) — was only declared in `composer.json` before, missing the actual file GitHub/Packagist expect.
- GitHub Actions CI (`.github/workflows/tests.yml`) — runs the suite against PHP 8.2/8.3/8.4 on every push and PR.
- Test suite grew to 47 tests / 135 assertions with all the new HTTP endpoints covered.

### Changed
- `composer.json`'s `description`/`keywords` rewritten to reflect the full HR/payroll scope (was still the original attendance-only wording from `v0.1.0`) — this is what Packagist displays.
- CI runs against a real MySQL 8 service container instead of sqlite in-memory, matching how this package is actually developed/tested day to day.

### Fixed
- **Cross-database date-comparison bug**, caught by CI: every `date`-cast column (`Holiday::date`, `Leave::start_date`/`end_date`, `EmployeeShift::start_date`/`end_date`, `AttendanceSummary::date`, `OvertimeRecord::date`) is written by Eloquent through the connection's full datetime format, which MySQL's `DATE` columns silently truncate on insert but SQLite stores verbatim — so every exact-string `where('date', ...)` comparison in `Holiday::onDate()`, `Leave::covers()`, `EmployeeShift::scopeCovering()`, `AttendanceSummaryService::buildOne()`, and `OvertimeRecord::detectFromSummary()` only ever matched on MySQL. Switched all of them to `whereDate()`, and replaced the two `updateOrCreate()` calls whose own internal lookup had the same problem (silently attempting a duplicate insert instead of finding the existing row) with an explicit `whereDate()` find first. No behavior change on MySQL; fixes summaries/leave/holidays/overtime on sqlite and any other driver.

### Published
- **Live on Packagist as `easybdit/laraveleasyattendance`** — `composer require` now works from any project, no path-repo needed.

## [0.3.0] - 2026-09-07

### Added
- Automated test suite (PHPUnit + Orchestra Testbench). Runs against sqlite in-memory by default, overridable via env vars for any other driver — see the README's Testing section.
- **HR core**, optional (`ATTENDANCE_FEATURE_HR_CORE=true` for all of it, or toggle each piece individually):
  - `Employee` — the package's own subject model (salary, allowances, device PIN); uses `HasAttendance` itself, so check-in/out and device sync work on it directly
  - `Shift` + `EmployeeShift` (schedule/roster) — working hours, late grace, off days, assigned per employee per date range; `ShiftResolver` falls back to `config('attendance.default_shift')` when no roster entry covers a date
  - `Holiday`, including recurring-yearly (same month/day, any year)
  - `Leave` (+ `LeaveType`) — request/approve/reject, same pattern as attendance corrections; an approved leave outranks even a stray punch
  - `AttendanceSummaryService` — daily present/late/absent/leave/holiday/day_off computation from the punch log + shift + leave + holiday, priority-ordered; `attendance:build-summaries` command
  - `SalaryService` — generates a `SalarySlip` from a month's summaries (rebuilding them first); absent days + every Nth late day (default 3) each dock one per-day rate — the common "3 late = 1 absent" office policy; `attendance:generate-salary` command
  - `AttendanceReportController` — read-only JSON reports: daily, monthly grid, employee-wise range, salary
  - **Overtime**: auto-detected from each day's summary (`ot_minutes`) as a `pending` `OvertimeRecord` — only reaches a payslip once `approve()`d, so a punch-clock quirk can't quietly inflate pay. Rate = `basic_salary / (salary_divisor × 8) × rate_multiplier` (BD Labour Act convention this package was first built under), capped at `max_hours_per_day`. Rebuilding a summary never reopens an already-reviewed record.
  - **Special working days**: extra pay for an employee asked to work a day normally off (their shift's off day, or a `Holiday`) — `type` auto-detected from the date against that employee's own shift, payment defaults from config (`daily_rate` / `fixed_amount` / `multiplier`) or a custom `payment_amount`, and only pays if the employee actually punched in that day.
  - Events: `LeaveRequested`, `LeaveReviewed`, `AttendanceMarkedLate`, `OvertimeReviewed`
- Test suite: 39 tests / 94 assertions — summary status priority, late-minute math, recurring holidays, leave overriding a punch, the late-ratio deduction boundary (2 vs. 3 late days), OT capped-and-approval-gated, special-day type detection, and pay withheld unless the employee showed up.

### Docs
- README's new HR core section includes two full worked examples (core HR flow, and overtime + special working day) — real, runnable scripts with the actual output alongside them, not just isolated per-feature snippets.

### Fixed
- `Holiday::on()` collided with `Eloquent\Model`'s own static `on($connection)` — incompatible signature, fatal error on any use. Renamed to `Holiday::onDate()`.

## [0.2.1] - 2026-09-07

### Docs
- README's device sync section rewritten as an explicit, numbered register → connect/sync → query walkthrough for each mode (Pull, Push/ADMS), plus a dedicated "Viewing synced data" section — the one rule that matters (nothing is visible until a sync actually runs) is now stated up front instead of implied.
- Added a Contents table of links and a full config/routes reference table.
- Added `CHANGELOG.md` (this file).

### Validated
- Rechecked the full pull-mode sequence step by step against a live device, calling the same controller code the HTTP routes use:
  - registering + testing a device fetches nothing by itself (confirmed zero rows for it beforehand)
  - `pull` — 7,136 real logs fetched in ~13s; PINs with no matching subject correctly reported instead of imported
  - with a subject's PIN set to match a real PIN from that log, a second pull landed 23 real historical punches (~4 months of real dates) on that subject, immediately queryable via `attendances()`/`attendanceOn()`

## [0.2.0] - 2026-09-07

### Added
- ZKTeco device sync, Tier 3, behind `ATTENDANCE_FEATURE_DEVICE_SYNC` (off by default):
  - `AttendanceDevice` model — unified pull (`ip`) / push (`serial_number`) device, with `connection_mode` and `is_online` accessors
  - `ZKService` — thin wrapper over `coding-libs/zkteco-php` for pull-mode connections
  - `AttendanceDeviceSyncService` — single `ingestLogs()` shared by both pull and push, so a punch is handled identically regardless of direction; dedups via a `(device_id, device_user_id, time)` unique constraint instead of trusting the caller not to double-import
  - `AdmsPushController` (`/iclock/cdata`, `/iclock/getrequest`, `/iclock/devicecmd`) — registered without the `web` middleware group, so there's no CSRF exemption to configure in the host app
  - `AttendanceDeviceController` — CRUD + on-demand `pull`/`test` over JSON
  - `attendance:sync-devices` console command for scheduling pull-mode devices; one device's failure never blocks the rest of the batch
  - `AttendanceDeviceSyncFailed` event, escalating (fires on the 2nd consecutive failure, then every 5th after that — configurable) instead of firing on every blip
  - Subject-PIN matching via a configurable `device_sync.pin_column` on the subject model, rather than assuming an `Employee`/`employee_code` shape

### Fixed
- `coding-libs/zkteco-php` was misnamed as `codinglibs/zkteco-php` (composer suggest, config comments, README, and the `ZKService` runtime error message) — caught while wiring up a real end-to-end test against physical hardware.

### Validated
- Push mode against a real device's genuine serial number — handshake + ATTLOG accepted, heartbeat updated.
- Pull mode against a live production device over the internet — 7,136 real logs fetched in ~13s, unmatched PINs correctly reported rather than dropped.

## [0.1.0] - 2026-09-07

### Added
- Core attendance tracking:
  - `Attendance` model — polymorphic `subject` (`morphs()`), so it attaches to any host app model (`User`, `Employee`, ...) instead of a hardcoded `employee_id`
  - `HasAttendance` trait — `checkIn()`, `checkOut()`, `attendanceOn()`, `requestAttendanceCorrection()`
  - `Attendance::resolveDayWindow()` — resolves a day's first-in/last-out from a punch log, giving manual entries priority over device/api punches
  - `AttendanceCorrection` model + workflow — `approve()`/`reject()`; an approval creates real punches (`source: correction`) instead of living as a side note
  - Events: `AttendanceRecorded`, `AttendanceCorrectionRequested`, `AttendanceCorrectionReviewed`
  - `attendance:install` console command (publishes config, runs migrations)
  - Built-in routes for check-in/out/today and correction request/review, prefix and middleware both configurable
  - `subject_model` defaults to the host app's auth user model — works with zero config out of the box

[Unreleased]: https://github.com/easybdit/laraveleasyattendance/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/easybdit/laraveleasyattendance/compare/v0.3.0...v0.4.0
