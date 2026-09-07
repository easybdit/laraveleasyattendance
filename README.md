# LaravelEasyAttendance

Drop-in attendance tracking (check-in / check-out, corrections) for any Laravel app — works against your existing `User` model out of the box, or any model you choose.

## Install

```bash
composer require easybdit/laraveleasyattendance
php artisan attendance:install
```

Add the trait to whichever model represents "who checks in" (defaults to your auth user model):

```php
use Easybdit\LaravelEasyAttendance\Traits\HasAttendance;

class User extends Authenticatable
{
    use HasAttendance;
}
```

## Usage

```php
$user->checkIn();
$user->checkOut();
$user->attendanceOn('2026-09-07'); // ['first_in' => ..., 'last_out' => ...]
$user->attendances; // MorphMany
```

Or via the built-in routes (auth-protected, prefixed `/attendance`):

```
POST /attendance/check-in
POST /attendance/check-out
GET  /attendance/today
```

## Configuration

Publish and edit `config/attendance.php` to change the subject model, route prefix/middleware, and feature toggles (corrections, device sync, summaries — device sync and summaries ship in later tiers).

## Using a different subject model

```
ATTENDANCE_SUBJECT_MODEL=App\\Models\\Employee
```

Add `HasAttendance` to that model instead of `User`.

## ZKTeco device sync (optional)

```
ATTENDANCE_FEATURE_DEVICE_SYNC=true
```

Then re-run migrations (adds `attendance_devices` + device columns on `attendances`). Two sync modes, side by side — use whichever fits a given device:

- **Pull** — the server connects out to the device's IP. Needs `composer require coding-libs/zkteco-php`. Trigger it via `POST /attendance/devices/{id}/pull`, or schedule `php artisan attendance:sync-devices` so backlogs stay small.
- **Push (ADMS)** — the device dials home to `/iclock/cdata` etc. Point its "Cloud Server" setting at your app's domain. No extra package needed, and no CSRF setup either — these routes are registered outside the `web` middleware group.

Your subject model needs a PIN column (default `device_user_id`) holding each person's device PIN — add it yourself (`config('attendance.device_sync.pin_column')` to rename it). A punch with no matching PIN is skipped and reported back, not silently dropped.

Listen for `AttendanceDeviceSyncFailed` to alert on a device that's stopped syncing.
