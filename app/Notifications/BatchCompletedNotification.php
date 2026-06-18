<?php

namespace App\Notifications;

use App\Models\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BatchCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $batch;

    public function __construct(Batch $batch)
    {
       
        $this->batch = $batch;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
      
        $batch = $this->batch;
        // Load all relations needed for the PDF report
        $batch->load([
            'workOrder.plant.entity',
            'workOrder.mixDesign.concrete_grade',
            'workOrder.customer',
            'workOrder.site',
            'site',
            'dispatches.truck',
            'dispatches.driver',
            'materials.product.category',
            'materials.uom',
        ]);
        
        $sheet = $batch->getReportData();
        // Generate PDF in memory
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.batches.batch_sheet', [
            'batch' => $batch,
            'sheet' => $sheet,
            'isPreview' => false,
        ])->setPaper('a4', 'landscape');

        $filename = 'BatchReport_#' . $batch->batch_no . '_Order_' . ($batch->workOrder->order_no ?? 'N/A') . '.pdf';

        return (new MailMessage)
            ->subject('Batching Complete - Batch #' . $batch->batch_no)
            ->greeting('Industrial Ops Update')
            ->line('The batching process for Order **#' . ($batch->workOrder->order_no ?? 'N/A') . '** has been completed successfully.')
            ->line('**Production Summary:**')
            ->line('Customer: ' . ($batch->workOrder->customer->legal_name ?? 'N/A'))
            ->line('Site: ' . ($batch->workOrder->site->name ?? 'N/A'))
            ->line('Batch Size: ' . $batch->batch_size . ' m³')
            ->action('View Online Report', route('batches.report', $batch->id))
            ->attachData($pdf->output(), $filename, [
                'mime' => 'application/pdf',
            ])
            ->line('The detailed batching report has been attached to this email as a PDF.')
            ->line('Thank you for choosing onemodo.com!');
    }
}