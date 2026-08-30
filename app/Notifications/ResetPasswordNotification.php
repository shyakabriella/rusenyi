<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable->getEmailForPasswordReset();

        $frontendUrl = rtrim(
            (string) config(
                'frontend.url',
                'http://localhost:3000'
            ),
            '/'
        );

        $resetUrl = $frontendUrl
            . '/reset-password?'
            . http_build_query([
                'token' => $this->token,
                'email' => $email,
            ]);

        $expire = config(
            'auth.passwords.'
            . config('auth.defaults.passwords')
            . '.expire',
            60
        );

        return (new MailMessage)
            ->subject('Reset Your Password')
            ->greeting(
                'Hello ' . $notifiable->name . ','
            )
            ->line(
                'We received a request to reset your password.'
            )
            ->line(
                'Click the button below to create a new password.'
            )
            ->action(
                'Reset Password',
                $resetUrl
            )
            ->line(
                "This password reset link will expire in {$expire} minutes."
            )
            ->line(
                'If you did not request a password reset, you can safely ignore this email.'
            )
            ->salutation(
                'Regards, Gihombo Coffee Washing Station'
            );
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
