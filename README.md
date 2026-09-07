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
