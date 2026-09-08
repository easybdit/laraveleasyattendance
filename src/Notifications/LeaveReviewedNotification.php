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

    public function __construct(public Leave $leave)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = ucfirst($this->leave->status);

        return (new MailMessage)
            ->subject("Leave request {$status}")
            ->line("Your leave request for {$this->leave->start_date->toDateString()} to {$this->leave->end_date->toDateString()} was {$this->leave->status}.")
            ->when($this->leave->review_note, fn ($mail) => $mail->line("Note: {$this->leave->review_note}"));
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
