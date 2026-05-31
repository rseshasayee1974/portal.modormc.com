<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $reportType;
    public $plantName;
    public $frequency;
    public $dateRange;
    protected $attachmentData;
    protected $attachmentName;
    protected $mimeType;

    /**
     * Create a new message instance.
     */
    public function __construct(
        $reportType,
        $plantName,
        $frequency,
        $dateRange,
        $attachmentData,
        $attachmentName,
        $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ) {
        $this->reportType = $reportType;
        $this->plantName = $plantName;
        $this->frequency = $frequency;
        $this->dateRange = $dateRange;
        $this->attachmentData = $attachmentData;
        $this->attachmentName = $attachmentName;
        $this->mimeType = $mimeType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '[%s] Scheduled %s Report (%s)',
                $this->plantName,
                ucfirst(str_replace('_', ' ', $this->reportType)),
                ucfirst($this->frequency)
            )
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.scheduled_report',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => $this->attachmentData,
                $this->attachmentName
            )->withMime($this->mimeType)
        ];
    }
}
