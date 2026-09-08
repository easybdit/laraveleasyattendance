<?php

namespace Easybdit\LaravelEasyAttendance\Notifications;

use Easybdit\LaravelEasyAttendance\Models\OvertimeRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ready-made content for OvertimeReviewed — check $record->status
 * (approved or rejected) for which.
 */
class OvertimeReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(public OvertimeRecord $record)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('attendance::notifications.overtime_reviewed.subject', ['status' => ucfirst($this->record->status), 'date' => $this->record->date->toDateString()]))
            ->line(__('attendance::notifications.overtime_reviewed.line', ['date' => $this->record->date->toDateString(), 'hours' => $this->record->ot_hours, 'status' => $this->record->status]))
            ->when($this->record->note, fn ($mail) => $mail->line(__('attendance::notifications.overtime_reviewed.note_line', ['note' => $this->record->note])));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'overtime_id' => $this->record->id,
            'status' => $this->record->status,
            'ot_hours' => (float) $this->record->ot_hours,
            'ot_amount' => (float) $this->record->ot_amount,
        ];
    }
}
