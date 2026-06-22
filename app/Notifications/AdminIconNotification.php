<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminIconNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $details;

    public function __construct(array $details)
    {
        $this->details = $details;
    }

    public function via($notifiable)
    {
        return ['database']; // can add 'mail' or 'broadcast' later
    }

    public function toDatabase($notifiable)
    {
        return new DatabaseMessage([
            'type'      => $this->details['type'],
            'title'     => $this->details['title'],
            'message'   => $this->details['message'],
            'sender_id' => $this->details['sender_id'],
        ]);
    }
}
