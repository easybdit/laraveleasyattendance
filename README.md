# LaravelEasyAttendance

Drop-in attendance tracking — check-in/out, correction requests, and ZKTeco biometric device sync (pull *and* push/ADMS) — for any Laravel app. Works against your existing `User` model out of the box, or any model you choose.

Extracted and redesigned from a production HR system's attendance module, and validated end-to-end against real ZKTeco hardware (see [Tested against real devices](#tested-against-real-devices)).

## Contents

- [Install](#install)
- [Quick start](#quick-start)
- [Core concept: the subject model](#core-concept-the-subject-model)
- [How a punch is resolved](#how-a-punch-is-resolved)
- [Corrections](#corrections)
- [Events](#events)
- [ZKTeco device sync](#zkteco-device-sync)
- [Configuration reference](#configuration-reference)
- [Routes reference](#routes-reference)
- [Tested against real devices](#tested-against-real-devices)
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

The package has no opinion on notifications — listen for these and send however your app already does.

## ZKTeco device sync

```
ATTENDANCE_FEATURE_DEVICE_SYNC=true
```

Then re-run migrations (adds `attendance_devices` + `device_id`/`device_user_id` columns on `attendances`, unique-constrained so the same punch can never be imported twice). Two sync modes, side by side — pick whichever fits a given device, or run both against different devices at once:

**Pull** — your server connects out to the device's IP on the local network.
```bash
composer require coding-libs/zkteco-php
```
```
POST /attendance/devices/{id}/pull        # on demand
php artisan attendance:sync-devices       # scheduled — keeps backlogs small
```

**Push (ADMS)** — the device dials home to you instead (for a device your server can't reach directly: remote site, no static IP, no VPN). Point its "Cloud Server" setting at your app's domain, Server Mode `ADMS`:
```
GET|POST /iclock/cdata
GET      /iclock/getrequest
POST     /iclock/devicecmd
```
No extra package needed. These routes are deliberately registered **without** the `web` middleware group — a device firmware can't carry a session or a CSRF token — so unlike most ADMS implementations, there's nothing to exempt in your `bootstrap/app.php`.

Both modes funnel through one shared `AttendanceDeviceSyncService::ingestLogs()`, so a punch is handled identically no matter which direction it arrived from. Your subject model needs a PIN column (default `device_user_id`, rename via `config('attendance.device_sync.pin_column')`) holding each person's device PIN — a punch whose PIN matches nobody is **skipped and reported back** (`unmatched` in the response, logged on push), never silently dropped.

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
| `features.summaries` | `false` | Reserved — Tier 2, not yet implemented |
| `device_sync.pin_column` | `device_user_id` | Column on the subject model's table holding the device PIN |
| `device_sync.online_threshold_seconds` | `90` | How recently a push device must have been seen to count "online" |
| `device_sync.notify_after_failures` / `notify_every` | `2` / `5` | `AttendanceDeviceSyncFailed` escalation schedule |

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

## Tested against real devices

Not just unit-tested against fixtures — validated against actual production ZKTeco hardware during development:

- **Push (ADMS):** simulated a real device's ATTLOG push (its genuine serial number) against `/iclock/cdata` — handshake accepted, punch ingested, heartbeat updated.
- **Pull (IP):** connected directly to a live device over the internet — **7,136 real attendance logs fetched in ~13 seconds**, with every PIN that didn't match a known subject correctly reported instead of silently dropped.

## Roadmap

- **Tier 2 — summaries/shift rules** (optional): daily present/late/absent/holiday computation via `Contracts\ShiftResolver` / `LeaveChecker` / `HolidayChecker`, so your app supplies real shift/leave/holiday logic instead of the package guessing at it.
- Packagist publish.

## License

MIT.
