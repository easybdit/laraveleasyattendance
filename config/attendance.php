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
        'summaries'   => env('ATTENDANCE_FEATURE_SUMMARIES', env('ATTENDANCE_FEATURE_HR_CORE', false)),

        // HR core — each independently toggleable (off by default, so a
        // pure check-in/out install never pays for tables it doesn't use),
        // but every one of them falls back to ATTENDANCE_FEATURE_HR_CORE
        // when not set individually — set that one var true to turn the
        // whole employee/shift/holiday/leave/summary/salary stack on at
        // once instead of six separate env lines.
        'employees' => env('ATTENDANCE_FEATURE_EMPLOYEES', env('ATTENDANCE_FEATURE_HR_CORE', false)),
        'shifts'    => env('ATTENDANCE_FEATURE_SHIFTS', env('ATTENDANCE_FEATURE_HR_CORE', false)),
        'holidays'  => env('ATTENDANCE_FEATURE_HOLIDAYS', env('ATTENDANCE_FEATURE_HR_CORE', false)),
        'leave'     => env('ATTENDANCE_FEATURE_LEAVE', env('ATTENDANCE_FEATURE_HR_CORE', false)),
        'salary'    => env('ATTENDANCE_FEATURE_SALARY', env('ATTENDANCE_FEATURE_HR_CORE', false)),
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
        'end_time'   => '18:00:00',
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

        // Escalating sync-failure alert: fire AttendanceDeviceSyncFailed
        // the Nth failure in a row, then again every M failures after
        // that, so one blip doesn't spam but a real outage doesn't go
        // silent either. Listen for the event to notify however you like.
        'notify_after_failures' => 2,
        'notify_every' => 5,
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

];
