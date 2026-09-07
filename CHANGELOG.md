# Changelog

All notable changes to this project are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/); this project follows [Semantic Versioning](https://semver.org/) once it reaches `1.0.0` — until then, minor versions may still include breaking changes.

## [Unreleased]

### Planned
- Tier 2: optional summaries/shift-rules engine (`Contracts\ShiftResolver`, `LeaveChecker`, `HolidayChecker`)
- Packagist publish

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

[Unreleased]: https://github.com/easybdit/laraveleasyattendance/compare/main...HEAD
