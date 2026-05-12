<?php

namespace App\Notifications;

use App\Models\Dispatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DispatchCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $dispatch;

    public function __construct(Dispatch $dispatch)
    {
        $this->dispatch = $dispatch;
    }

    public function via($notifiable): array
    {
        // Currently sending via Mail. WhatsApp logic is triggered separately or via custom channel.
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $d = $this->dispatch;
        $d->load(['customer', 'mixDesign', 'truck', 'workOrder', 'driver']);
        
        return (new MailMessage)
            ->subject('Dispatch Confirmation - Ticket #' . $d->dispatch_no)
            ->greeting('Hello ' . ($d->customer->legal_name ?? 'Valued Customer') . ',')
            ->line('We are pleased to inform you that your concrete dispatch has been processed.')
            ->line('**Dispatch Summary:**')
            ->line('Ticket Number: **' . $d->dispatch_no . '**')
            ->line('Order Number: ' . ($d->workOrder->order_no ?? 'N/A'))
            ->line('Quantity: **' . $d->delivered_qty . ' m³**')
            ->line('Mix Grade: ' . ($d->mixDesign->design_name ?? 'RMC'))
            ->line('Vehicle: ' . ($d->truck->registration ?? 'N/A'))
            ->line('Driver: ' . ($d->driver->first_name ?? 'N/A'))
            ->line('The vehicle is now en route to your site.')
            ->line('Thank you for choosing ModoRMC!')
            ->line('Powered by onemodo.com');
    }

    /**
     * Generate the WhatsApp message string for this dispatch.
     */
    public function toWhatsAppMessage(): string
    {
        $d = $this->dispatch;
        $d->load(['customer', 'mixDesign', 'truck', 'workOrder', 'driver']);
        
        $customerName = $d->customer->legal_name ?? 'Customer';
        
        return "🚚 *Dispatch Update - ModoRMC*\n\n"
             . "Hello *$customerName*,\n"
             . "Your RMC load is on the way! ✅\n\n"
             . "🎫 *Ticket:* #{$d->dispatch_no}\n"
             . "🏗️ *Order:* " . ($d->workOrder->order_no ?? 'N/A') . "\n"
             . "🧪 *Grade:* " . ($d->mixDesign->design_name ?? 'N/A') . "\n"
             . "📊 *Quantity:* {$d->delivered_qty} m³\n"
             . "🚛 *Vehicle:* " . ($d->truck->registration ?? 'N/A') . "\n"
             . "👤 *Driver:* " . ($d->driver->first_name ?? 'N/A') . "\n\n"
             . "Track your delivery on the portal.\n"
             . "Thank you for choosing *onemodo.com*!";
    }
}
