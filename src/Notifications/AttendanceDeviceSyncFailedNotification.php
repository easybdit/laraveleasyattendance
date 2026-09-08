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
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Attendance device \"{$this->device->name}\" isn't syncing")
            ->line("{$this->consecutiveFailures} sync attempt(s) in a row have failed: {$this->reason}")
            ->line('Last successful sync: '.($this->device->last_synced_at?->diffForHumans() ?? 'never').'.')
            ->when($this->device->ip, fn ($mail) => $mail->line("Check the device is powered on and reachable at {$this->device->ip}:{$this->device->port}."));
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
