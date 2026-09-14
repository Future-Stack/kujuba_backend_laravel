<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;
use App\Services\PushNotificationService;

class PlatformNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $details;

    public function __construct(array $details)
    {
        $this->details = $details;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $title       = $this->details['title'] ?? 'Notification';
        $message     = $this->details['message'] ?? '';
        $type        = $this->details['type'] ?? 'general';
        $senderId    = $this->details['sender_id'] ?? null;
        $bookingId   = $this->details['booking_id'] ?? null;
        $sentToLabel = $this->details['sent_to_label'] ?? null;

        if (!empty($notifiable->device_token)) {
            PushNotificationService::sendToDevice(
                $notifiable->device_token,
                $title,
                $message,
                [
                    'type'          => $type,
                    'booking_id'    => (string) ($bookingId ?? ''),
                    'sender_id'     => (string) ($senderId ?? ''),
                    'sent_to_label' => (string) ($sentToLabel ?? ''),
                ]
            );
        }

        return new DatabaseMessage([
            'type'          => $type,
            'title'         => $title,
            'message'       => $message,
            'sender_id'     => $senderId,
            'booking_id'    => $bookingId,
            'sent_to_label' => $sentToLabel,
        ]);
    }
}
