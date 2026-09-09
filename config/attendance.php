<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subject model
    |--------------------------------------------------------------------------
    |
    | The model that "has" attendance — the person being checked in/out.
    | Defaults to your app's auth user model so the package works out of
    | the box. Point this at your own model (e.g. App\Models\Employee)
    | if attendance belongs to something other than your User model.
    |
    */
    'subject_model' => env('ATTENDANCE_SUBJECT_MODEL', config('auth.providers.users.model', 'App\\Models\\User')),

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    |
    | Every table this package owns, prefixed `easyattendance_` by default
    | so a generic-sounding name like "employees" or "leaves" can't collide
    | with a table your app (or another package) already has. Override any
    | one of these if you still hit a conflict, or want the package to read
    | an existing table instead — same shape as spatie/laravel-permission's
    | `table_names` config, so nothing new to learn if you've used that.
    | Every model resolves its table from here (see Models/Concerns/HasPackageTable),
    | and every migration's Schema::create()/constrained() calls read the
    | same array, so changing a value here before your first `migrate` is
    | all it takes — no need to touch a model or migration yourself.
    |
    */
    'table_names' => [
        'attendances' => 'easyattendance_attendances',
        'attendance_corrections' => 'easyattendance_corrections',
        'attendance_devices' => 'easyattendance_devices',
        'attendance_summaries' => 'easyattendance_summaries',
        'employees' => 'easyattendance_employees',
        'employee_shifts' => 'easyattendance_employee_shifts',
        'shifts' => 'easyattendance_shifts',
        'holidays' => 'easyattendance_holidays',
        'leave_types' => 'easyattendance_leave_types',
        'leaves' => 'easyattendance_leaves',
        'salary_slips' => 'easyattendance_salary_slips',
        'overtime_records' => 'easyattendance_overtime_records',
        'special_working_days' => 'easyattendance_special_working_days',
        'departments' => 'easyattendance_departments',
        'designations' => 'easyattendance_designations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route registration
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => env('ATTENDANCE_ROUTES_ENABLED', true),
        'prefix' => env('ATTENDANCE_ROUTE_PREFIX', 'attendance'),
        'middleware' => ['web', 'auth'],

        // Extra middleware layered on top of the above for the
        // approve/reject endpoints only. Point this at your own
        // admin/HR gate, e.g. ['web', 'auth', 'can:review-attendance'].
        'review_middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature toggles
    |--------------------------------------------------------------------------
    |
    | Turn optional sub-modules on/off. Each one only loads its routes,
    | migrations and bindings when enabled — keeps a minimal install lean.
    |
    */
    'features' => [
        'corrections' => env('ATTENDANCE_FEATURE_CORRECTIONS', true),
        'device_sync' => env('ATTENDANCE_FEATURE_DEVICE_SYNC', false),
        'summaries' => env('ATTENDANCE_FEATURE_SUMMARIES', env('ATTENDANCE_FEATURE_HR_CORE', false)),

        // HR core — each independently toggleable (off by default, so a
        // pure check-in/out install never pays for tables it doesn't use),
        // but every one of them falls back to ATTENDANCE_FEATURE_HR_CORE
        // when not set individually — set that one var true to turn the
        // whole employee/shift/holiday/leave/summary/salary stack on at
        // once instead of six separate env lines.
        'employees' => env('ATTENDANCE_FEATURE_EMPLOYEES', env('ATTENDANCE_FEATURE_HR_CORE', false)),
        'shifts' => env('ATTENDANCE_FEATURE_SHIFTS', env('ATTENDANCE_FEATURE_HR_CORE', false)),
        'holidays' => env('ATTENDANCE_FEATURE_HOLIDAYS', env('ATTENDANCE_FEATURE_HR_CORE', false)),
        'leave' => env('ATTENDANCE_FEATURE_LEAVE', env('ATTENDANCE_FEATURE_HR_CORE', false)),
        'salary' => env('ATTENDANCE_FEATURE_SALARY', env('ATTENDANCE_FEATURE_HR_CORE', false)),
        'overtime' => env('ATTENDANCE_FEATURE_OVERTIME', env('ATTENDANCE_FEATURE_HR_CORE', false)),
        'special_working_days' => env('ATTENDANCE_FEATURE_SPECIAL_WORKING_DAYS', env('ATTENDANCE_FEATURE_HR_CORE', false)),
        'departments' => env('ATTENDANCE_FEATURE_DEPARTMENTS', env('ATTENDANCE_FEATURE_HR_CORE', false)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Work window
    |--------------------------------------------------------------------------
    |
    | Fallback shift used by ShiftResolver for an employee with no Shift
    | assigned via employee_shifts (schedule) — so summaries/salary still
    | work the moment you enable them, before you've set up any shifts.
    |
    */
    'default_shift' => [
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'late_grace_minutes' => 15,
        'week_off_day' => 'Friday', // Carbon day name, or null for none
    ],

    /*
    |--------------------------------------------------------------------------
    | Device sync (ZKTeco / compatible biometric devices)
    |--------------------------------------------------------------------------
    |
    | Only active when features.device_sync is true. Requires
    | coding-libs/zkteco-php (composer require coding-libs/zkteco-php) for
    | pull-mode devices; push/ADMS mode needs nothing extra since the
    | device talks to us over plain HTTP.
    |
    | pin_column: the column on your subject model's table that holds the
    | device PIN (the ZK device's own numeric user id) — used to match an
    | incoming punch to a subject. Add this column yourself (a plain
    | nullable string is enough); the package doesn't migrate your subject
    | table for you since it doesn't own it.
    |
    */
    'device_sync' => [
        'pin_column' => env('ATTENDANCE_DEVICE_PIN_COLUMN', 'device_user_id'),
        'online_threshold_seconds' => 90,

        // Socket receive timeout for a pull-mode connection attempt.
        // Lower this if you'd rather fail fast against a device that's
        // usually reachable in well under a second on a local network.
        'pull_timeout_seconds' => env('ATTENDANCE_DEVICE_PULL_TIMEOUT', 15),

        // Escalating sync-failure alert: fire AttendanceDeviceSyncFailed
        // the Nth failure in a row, then again every M failures after
        // that, so one blip doesn't spam but a real outage doesn't go
        // silent either. Listen for the event to notify however you like.
        'notify_after_failures' => 2,
        'notify_every' => 5,

        // ADMS push (Mode B) hardening — see the README's "ZKTeco
        // Biometric Attendance & Device Sync" section. The device
        // firmware itself can't carry any auth beyond its serial number,
        // so these three are the package's own mitigations on top of that.
        //
        // adms_verify_ip: reject a push whose source IP doesn't match the
        // device's registered `ip` column, when one is set. Off by
        // default — most push devices sit behind NAT/a dynamic IP or a
        // reverse proxy that changes the source address, which is exactly
        // why push mode exists; only turn this on if you know the
        // device's IP is stable and reaches you directly.
        'adms_verify_ip' => env('ATTENDANCE_ADMS_VERIFY_IP', false),

        // adms_throttle: rate limit applied to all four ADMS routes
        // (Laravel's inline `throttle:max,minutes` syntax — no named
        // limiter to register). These routes are public and
        // unauthenticated by protocol necessity, so this is the one
        // built-in guard against a flood of requests. A real device
        // polls every ~30s; 60/min comfortably covers that with room to
        // spare, tighten it if you have very few devices.
        'adms_throttle' => env('ATTENDANCE_ADMS_THROTTLE', 'throttle:60,1'),

        // adms_max_lines_per_push: a single push batch is capped at this
        // many ATTLOG lines — anything beyond is logged and dropped
        // rather than processed, so a malformed or oversized POST body
        // can't turn into an unbounded amount of work per request. A real
        // device's backlog between syncs is realistically in the
        // hundreds, not tens of thousands.
        'adms_max_lines_per_push' => env('ATTENDANCE_ADMS_MAX_LINES_PER_PUSH', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Salary generation
    |--------------------------------------------------------------------------
    |
    | Only active when features.salary is true. A deliberately simple,
    | override-able default rule: absent days each dock one full per-day
    | rate; every Nth late day in the month docks one too (matches the
    | "late N times = 1 absent" policy common in Bangladeshi offices this
    | package was first built for) — read SalaryService if yours differs.
    |
    */
    'salary' => [
        'working_days_per_month' => env('ATTENDANCE_SALARY_WORKING_DAYS', 30),
        'late_deduction_ratio' => env('ATTENDANCE_SALARY_LATE_RATIO', 3), // every 3rd late day = 1 absent
        'deduct_for_absent' => true,
        'deduct_for_late' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Overtime
    |--------------------------------------------------------------------------
    |
    | Only active when features.overtime is true. Auto-detected from each
    | day's summary (AttendanceSummary::ot_minutes — last_out past the
    | shift's end_time), but lands as a 'pending' OvertimeRecord: OT pay
    | only reaches a salary slip once approved, so a punch-clock mistake
    | (or someone lingering after hours for no work reason) can't quietly
    | inflate pay. Rate formula mirrors the BD Labour Act convention this
    | package was first built under: hourly_rate = basic / (divisor × 8);
    | OT pays double that. Adjust divisor/multiplier for your own rules.
    |
    */
    'overtime' => [
        'auto_detect' => env('ATTENDANCE_OT_AUTO_DETECT', true),
        'salary_divisor' => env('ATTENDANCE_OT_SALARY_DIVISOR', 26), // basic / (divisor × 8) = hourly rate
        'rate_multiplier' => env('ATTENDANCE_OT_RATE_MULTIPLIER', 2), // OT pays this × the hourly rate
        'max_hours_per_day' => env('ATTENDANCE_OT_MAX_HOURS_PER_DAY', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Special working days
    |--------------------------------------------------------------------------
    |
    | Only active when features.special_working_days is true. For when an
    | employee is specifically asked to work a day off (their shift's
    | off_day) or a holiday — SpecialWorkingDay::save() auto-detects which
    | based on that date, and its extra payment (on top of ordinary salary)
    | is controlled by these config values unless a custom payment_amount
    | is set on the record itself.
    |
    */
    'special_working_days' => [
        // 'daily_rate' | 'fixed_amount' | 'multiplier'
        'day_off_payment_type' => env('ATTENDANCE_SPECIAL_DAY_OFF_PAYMENT_TYPE', 'daily_rate'),
        'day_off_fixed_amount' => env('ATTENDANCE_SPECIAL_DAY_OFF_FIXED_AMOUNT', 1000),
        'day_off_multiplier' => env('ATTENDANCE_SPECIAL_DAY_OFF_MULTIPLIER', 1.0),

        'holiday_payment_type' => env('ATTENDANCE_SPECIAL_HOLIDAY_PAYMENT_TYPE', 'daily_rate'),
        'holiday_fixed_amount' => env('ATTENDANCE_SPECIAL_HOLIDAY_FIXED_AMOUNT', 1000),
        'holiday_multiplier' => env('ATTENDANCE_SPECIAL_HOLIDAY_MULTIPLIER', 1.0),
    ],

];
