<?php

namespace Easybdit\LaravelEasyAttendance\Notifications;

use Easybdit\LaravelEasyAttendance\Models\AttendanceDevice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceDeviceSyncFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public AttendanceDevice $device,
        public string $reason,
        public int $consecutiveFailures,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('attendance::notifications.device_sync_failed.subject', ['name' => $this->device->name]))
            ->line(__('attendance::notifications.device_sync_failed.line', ['count' => $this->consecutiveFailures, 'reason' => $this->reason]))
            ->line(__('attendance::notifications.device_sync_failed.last_sync_line', [
                'when' => $this->device->last_synced_at?->diffForHumans() ?? __('attendance::notifications.device_sync_failed.never'),
            ]))
            ->when($this->device->ip, fn ($mail) => $mail->line(__('attendance::notifications.device_sync_failed.check_line', [
                'address' => "{$this->device->ip}:{$this->device->port}",
            ])));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'device_id' => $this->device->id,
            'device_name' => $this->device->name,
            'reason' => $this->reason,
            'consecutive_failures' => $this->consecutiveFailures,
        ];
    }
}
