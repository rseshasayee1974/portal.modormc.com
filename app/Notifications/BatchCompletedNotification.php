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
            ->line('<div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 20px 0;">
                <h4 style="margin: 0 0 12px 0; color: #4f46e5; font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Production Summary</h4>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px; font-family: sans-serif;">
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 10px 0; color: #64748b; font-weight: 600; width: 120px;">Customer</td>
                        <td style="padding: 10px 0; color: #0f172a; font-weight: 700;">' . ($batch->workOrder->customer->legal_name ?? 'N/A') . '</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 10px 0; color: #64748b; font-weight: 600;">Site</td>
                        <td style="padding: 10px 0; color: #0f172a; font-weight: 700;">' . ($batch->workOrder->site->name ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0; color: #64748b; font-weight: 600;">Batch Size</td>
                        <td style="padding: 10px 0; color: #4f46e5; font-weight: 800; font-size: 15px;">' . $batch->batch_size . ' m³</td>
                    </tr>
                </table>
            </div>')
            ->action('View Online Report', route('batches.report', \Illuminate\Support\Facades\Crypt::encryptString($batch->id)))
            ->attachData($pdf->output(), $filename, [
                'mime' => 'application/pdf',
            ])
            ->line('The detailed batching report has been attached to this email as a PDF.')
            ->line('Thank you for choosing **Modormc**!');
    }
}