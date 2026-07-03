<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class LoginOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $otp;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otp)
    {
        $this->otp = $otp;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Secure Login OTP - ModoRmc')
            ->greeting('Hello ' . ($notifiable->username ?? 'User') . ',')
            ->line('You requested a secure, password-less login to your **ModoRmc** account.')
            ->line(new HtmlString('<div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 20px 0; text-align: center;">
                <h4 style="margin: 0 0 10px 0; color: #64748b; font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Your One-Time Password</h4>
                <div style="font-size: 32px; font-weight: 900; color: #4f46e5; font-family: monospace; letter-spacing: 4px; margin: 15px 0;">' . $this->otp . '</div>
                <p style="margin: 0; color: #94a3b8; font-size: 11px; font-weight: 600; text-transform: uppercase;">Expires in 5 minutes</p>
            </div>'))
            ->line('Please enter this code on the login page to complete your sign-in process.')
            ->line('If you did not request this OTP, no action is required.')
            ->line('Thank you for choosing **Modormc**!');
    }
}
