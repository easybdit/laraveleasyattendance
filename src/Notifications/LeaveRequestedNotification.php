<?php

namespace Easybdit\LaravelEasyAttendance\Notifications;

use Easybdit\LaravelEasyAttendance\Models\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ready-made content for LeaveRequested — wire it up in your own app:
 *
 *   Event::listen(LeaveRequested::class, function ($event) {
 *       Notification::send($approvers, new LeaveRequestedNotification($event->leave));
 *   });
 */
class LeaveRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public Leave $leave)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->leave->employee;

        return (new MailMessage)
            ->subject(__('attendance::notifications.leave_requested.subject', ['name' => $employee->name]))
            ->line(__('attendance::notifications.leave_requested.line', [
                'name' => $employee->name, 'code' => $employee->employee_code,
                'start' => $this->leave->start_date->toDateString(), 'end' => $this->leave->end_date->toDateString(),
            ]))
            ->line($this->leave->reason
                ? __('attendance::notifications.leave_requested.reason_line', ['reason' => $this->leave->reason])
                : __('attendance::notifications.leave_requested.no_reason_line'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'leave_id' => $this->leave->id,
            'employee_id' => $this->leave->employee_id,
            'start_date' => $this->leave->start_date->toDateString(),
            'end_date' => $this->leave->end_date->toDateString(),
        ];
    }
}
