<?php

namespace Easybdit\LaravelEasyAttendance\Notifications;

use Easybdit\LaravelEasyAttendance\Models\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ready-made content for LeaveReviewed — check $leave->status (approved
 * or rejected) for which. Wire it up in your own app:
 *
 *   Event::listen(LeaveReviewed::class, function ($event) {
 *       $event->leave->employee->notify(new LeaveReviewedNotification($event->leave));
 *   });
 *
 * (Only works if your Employee model itself is Notifiable, or is also
 * your subject_model / linked to a notifiable user — otherwise send to
 * whoever in your app should hear about it instead.)
 */
class LeaveReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(public Leave $leave) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('attendance::notifications.leave_reviewed.subject', ['status' => ucfirst($this->leave->status)]))
            ->line(__('attendance::notifications.leave_reviewed.line', [
                'start' => $this->leave->start_date->toDateString(), 'end' => $this->leave->end_date->toDateString(), 'status' => $this->leave->status,
            ]))
            ->when($this->leave->review_note, fn ($mail) => $mail->line(__('attendance::notifications.leave_reviewed.note_line', ['note' => $this->leave->review_note])));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'leave_id' => $this->leave->id,
            'status' => $this->leave->status,
            'review_note' => $this->leave->review_note,
        ];
    }
}
