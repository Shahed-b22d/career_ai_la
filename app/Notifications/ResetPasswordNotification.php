<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\ResetPassword;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Reset Your CareerAI Password')
            ->greeting("Hello {$notifiable->name}!")
            ->line('We received a request to reset your CareerAI account password.')
            ->action('Reset Password', $url)
            ->line('This link will expire in 60 minutes.')
            ->line('If you did not request a password reset, no action is needed.')
            ->salutation('The CareerAI Team');
    }

    protected function resetUrl($notifiable): string
    {
        // Deep link back to the app — Flutter will handle this URL
        return url('/reset-password') . '?token=' . $this->token . '&email=' . urlencode($notifiable->email);
    }
}
