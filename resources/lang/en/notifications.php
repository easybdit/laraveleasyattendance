<?php

return [

    'late' => [
        'subject' => 'Late attendance — :name',
        'line' => ':name (:code) checked in late on :date.',
        'minutes_line' => 'Late by :minutes minute(s).',
    ],

    'leave_requested' => [
        'subject' => 'Leave request — :name',
        'line' => ':name (:code) requested leave from :start to :end.',
        'reason_line' => 'Reason: :reason',
        'no_reason_line' => 'No reason given.',
    ],

    'leave_reviewed' => [
        'subject' => 'Leave request :status',
        'line' => 'Your leave request for :start to :end was :status.',
        'note_line' => 'Note: :note',
    ],

    'overtime_reviewed' => [
        'subject' => 'Overtime :status — :date',
        'line' => 'Your overtime for :date (:hours h) was :status.',
        'note_line' => 'Note: :note',
    ],

    'device_sync_failed' => [
        'subject' => 'Attendance device ":name" isn\'t syncing',
        'line' => ':count sync attempt(s) in a row have failed: :reason',
        'last_sync_line' => 'Last successful sync: :when.',
        'check_line' => 'Check the device is powered on and reachable at :address.',
        'never' => 'never',
    ],

];
