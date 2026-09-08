<?php

namespace Easybdit\LaravelEasyAttendance\Notifications;

use Easybdit\LaravelEasyAttendance\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ready-made content for AttendanceMarkedLate — the package fires that
 * event but has no opinion on who to tell, so it doesn't send this
 * itself. Wire it up in your own app, e.g.:
 *
 *   Event::listen(AttendanceMarkedLate::class, function ($event) {
 *       Notification::send($hrUsers, new AttendanceMarkedLateNotification(
 *           $event->employee, $event->date, $event->lateMinutes
 *       ));
 *   });
 *
 * Uses only illuminate/notifications (already part of any Laravel app) —
 * mail and database channels, both built in, no extra package.
 */
class AttendanceMarkedLateNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Employee $employee,
        public string $date,
        public int $lateMinutes,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Late attendance — {$this->employee->name}")
            ->line("{$this->employee->name} ({$this->employee->employee_code}) checked in late on {$this->date}.")
            ->line("Late by {$this->lateMinutes} minute(s).");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->name,
            'date' => $this->date,
            'late_minutes' => $this->lateMinutes,
        ];
    }
}
