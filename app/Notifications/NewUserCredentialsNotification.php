<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserCredentialsNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $temporaryPassword
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Gihombo System Account')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An account has been created for you in the Gihombo Coffee Management System.')
            ->line('Email: '.$notifiable->email)
            ->line('Temporary Password: '.$this->temporaryPassword)
            ->line('Role: '.ucfirst($notifiable->role))
            ->line('Please use these credentials to log in.')
            ->line('You will be required to change your temporary password after your first login.')
            ->line('Please keep your login credentials secure.');
    }
}
