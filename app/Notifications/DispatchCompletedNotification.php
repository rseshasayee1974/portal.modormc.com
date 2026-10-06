<?php

namespace App\Notifications;

use App\Models\Dispatch;
use App\Models\Batch;
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
        $values = $this->toWhatsAppTemplateParameters();
        return implode("\n", [
            '📅 Date: ' . $values[0],
            '🏢 Party Name: ' . $values[1],
            '📍 Site Location: ' . $values[2],
            '📦 Order Qty: ' . $values[3] . ' m³',
            '🚚 Dispatch Qty: ' . $values[4] . ' m³',
            '📊 Balance Qty: ' . $values[5] . ' m³',
            '🏗️ Mix: ' . $values[6],
            '👷 Site Supervisor: ' . $values[7],
            '📞 Site Contact: ' . $values[8],
            '⏰ Dispatch Time: ' . $values[9],
            '👨‍🔧 Driver: ' . $values[10],
            '🚛 Vehicle No: ' . $values[11],
            '🔢 Batch No: ' . $values[12],
            '📋 Load No: ' . $values[13],
            '',
            '*Thank you.*',
        ]);
    }

    /** Values for {{1}} through {{14}} in the approved dispatch template. */
    public function toWhatsAppTemplateParameters(): array
    {
        $d = $this->dispatch;
        $d->loadMissing([
            'customer', 'truck', 'driver', 'batch.salesOrder.site',
            'batch.salesOrder.mixDesign.concreteGrade', 'status',
        ]);

        $order = $d->batch?->salesOrder;
        // A numeric `status` attribute can shadow the loaded status relationship.
        $statusRecord = $d->getRelation('status');
        $site = $order?->site;
        $mix = $order?->mixDesign;
        $date = $d->dispatch_time ?? $d->created_at;
        $date = $date?->copy()->setTimezone(config('app.timezone'));
        $driver = trim(($d->driver?->first_name ?? '') . ' ' . ($d->driver?->last_name ?? ''));
        $location = $site ? collect([
            $site->name, $site->site_address_1, $site->site_address_2,
            $site->city, $site->district, $site->state, $site->zipcode,
        ])->filter(fn ($part) => filled($part))->unique()->implode(', ') : 'N/A';

        // Quantities retain a meaningful third decimal while displaying at least two.
        $quantity = static function ($value): string {
            $formatted = number_format((float) $value, 3, '.', '');
            return str_ends_with($formatted, '0') ? substr($formatted, 0, -1) : $formatted;
        };
        $balance = $order ? $quantity(max(0, (float) $order->total_qty - (float) $order->produced_qty)) : 'N/A';
        $loadCount = $d->unload_site_id ? Batch::query()
            ->where('status', Batch::STATUS_COMPLETED)
            ->when($d->plant_id, fn ($query) => $query->where('plant_id', $d->plant_id))
            ->whereHas('dispatches', function ($query) use ($d) {
                $query->where('unload_site_id', $d->unload_site_id)
                    ->where(fn ($status) => $status->whereNull('dispatch_status')->orWhere('dispatch_status', '!=', 'Cancelled'));
            })->count() : null;

        return [
            $date?->format('d-m-Y') ?? 'N/A',
            $d->customer?->legal_name ?: 'N/A',
            $location ?: 'N/A',
            $order ? $quantity($order->total_qty) : 'N/A',
            $quantity($d->delivered_qty),
            $balance,
            $mix?->design_name ?: ($mix?->concreteGrade?->name ?: ($mix?->grade ?: 'N/A')),
            $statusRecord?->receiver_name ?: 'N/A',
            $statusRecord?->receive_mobile ?: 'N/A',
            $date?->format('h:i A') ?? 'N/A',
            $driver ?: 'N/A',
            $d->truck?->registration ?: 'N/A',
            (string) ($d->batch?->batch_no ?? 'N/A'),
            $loadCount !== null ? (string) $loadCount : 'N/A',
        ];
    }
}
