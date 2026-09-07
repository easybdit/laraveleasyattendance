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
  - [Mode A — Pull](#mode-a--pull-server-connects-out-to-the-device)
  - [Mode B — Push/ADMS](#mode-b--push--adms-the-device-connects-to-you)
  - [Viewing synced data](#viewing-synced-data)
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

Not just unit-tested against fixtures — validated end to end against actual production ZKTeco hardware, following the exact register → connect/sync → query sequence documented above:

- **Push (ADMS):** simulated a real device's ATTLOG push (its genuine serial number) against `/iclock/cdata` — handshake accepted, punch ingested, heartbeat updated.
- **Pull (IP), connection only:** registered a live device, tested the connection — succeeded, confirmed **zero** attendance rows existed for it beforehand (nothing is fetched just by registering/testing).
- **Pull (IP), full sync:** same device, `pull` — **7,136 real attendance logs fetched in ~13 seconds**. PINs with no matching subject were correctly reported (not silently dropped) and produced no rows.
- **Pull (IP), matched subject:** assigned a subject a real PIN seen in that log, pulled again — **23 real historical punches** (spanning roughly 4 months of real dates) landed on that subject and were immediately queryable via `$user->attendances()` and `$user->attendanceOn($date)`.

## Roadmap

- **Tier 2 — summaries/shift rules** (optional): daily present/late/absent/holiday computation via `Contracts\ShiftResolver` / `LeaveChecker` / `HolidayChecker`, so your app supplies real shift/leave/holiday logic instead of the package guessing at it.
- Packagist publish.

## License

MIT.
