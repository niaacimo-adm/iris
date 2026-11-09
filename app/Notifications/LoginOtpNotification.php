<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification
{
    public function __construct(
        private string $code,
        private int $minutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your IRIS login code'))
            ->view('emails.login-otp', [
                'name'    => $notifiable->name,
                'code'    => $this->code,
                'minutes' => $this->minutes,
            ]);
    }
}
