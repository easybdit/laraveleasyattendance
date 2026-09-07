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
        'summaries'   => env('ATTENDANCE_FEATURE_SUMMARIES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Work window
    |--------------------------------------------------------------------------
    |
    | Simple defaults used by the built-in (fallback) shift resolver when
    | the "summaries" feature is on and no custom resolver is bound.
    | Bind Easybdit\LaravelEasyAttendance\Contracts\ShiftResolver in your
    | own service provider to replace this with real shift/roster logic.
    |
    */
    'default_shift' => [
        'start_time' => '09:00:00',
        'end_time'   => '18:00:00',
        'late_grace_minutes' => 15,
        'week_off_day' => 'Friday', // Carbon day name, or null for none
    ],

];
