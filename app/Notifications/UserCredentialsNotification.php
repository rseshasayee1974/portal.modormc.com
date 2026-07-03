<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserCredentialsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $password;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $password)
    {
        $this->password = $password;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function pigeons(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the notification's delivery channels (default channels).
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
            ->subject('Welcome to ModoRmc - Your Account Credentials')
            ->greeting('Hello ' . ($notifiable->username ?? 'User') . ',')
            ->line('Your user account has been successfully created on **ModoRmc**.')
            ->line(new \Illuminate\Support\HtmlString('<div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 20px 0;">
                <h4 style="margin: 0 0 12px 0; color: #4f46e5; font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Your Account Credentials</h4>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px; font-family: sans-serif;">
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 10px 0; color: #64748b; font-weight: 600; width: 120px;">Email Address</td>
                        <td style="padding: 10px 0; color: #0f172a; font-weight: 700;">' . $notifiable->email . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0; color: #64748b; font-weight: 600;">Password</td>
                        <td style="padding: 10px 0; color: #4f46e5; font-weight: 800; font-family: monospace; font-size: 16px; letter-spacing: 0.5px;">' . $this->password . '</td>
                    </tr>
                </table>
            </div>'))
            ->action('Login to Portal', url('/'))
            ->line('For security reasons, we highly recommend changing your password immediately after your first login.')
            ->line('Thank you for choosing **Modormc**!');
    }
}
