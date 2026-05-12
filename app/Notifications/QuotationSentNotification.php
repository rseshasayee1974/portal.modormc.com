<?php

namespace App\Notifications;

use App\Models\Quotation;
use App\Services\PrintDataFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class QuotationSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $quotation;

    public function __construct(Quotation $quotation)
    {
        $this->quotation = $quotation;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $quotation = $this->quotation;
        
        // Ensure necessary relations are loaded for the formatter
        $quotation->load(['patron', 'site', 'plant.entity', 'items.mixDesign', 'creator']);
        
        $data = PrintDataFormatter::fromQuotation($quotation);
        
        $templateKey = PrintDataFormatter::resolveTemplateKey('quotations', $quotation->plant_id);
        $view = PrintDataFormatter::resolveView($templateKey);

        // Generate PDF
        $pdf = Pdf::loadView($view, ['data' => $data]);
        $filename = Str::slug(($data['doc_title'] ?? 'Quotation') . '_' . ($data['doc_no'] ?? $quotation->reference)) . '.pdf';

        return (new MailMessage)
            ->subject('Quotation: ' . $quotation->reference . ' - ' . ($quotation->patron->legal_name ?? ''))
            ->greeting('Hello!')
            ->line('Please find the formal quotation attached for your review.')
            ->line('**Quotation Details:**')
            ->line('Reference: ' . $quotation->reference)
            ->line('Date: ' . ($quotation->quote_date ? $quotation->quote_date->format('d-M-Y') : 'N/A'))
            ->line('Total Amount: ₹ ' . number_format($quotation->amount_total, 2))
            ->line('Validity: ' . ($quotation->validity_date ? $quotation->validity_date->format('d-M-Y') : 'N/A'))
            ->action('View Digital Version', route('quotations.report', $quotation->id))
            ->attachData($pdf->output(), $filename, [
                'mime' => 'application/pdf',
            ])
            ->line('If you have any questions, please feel free to reach out to us.')
            ->line('Thank you for choosing onemodo.com!');
    }
}
