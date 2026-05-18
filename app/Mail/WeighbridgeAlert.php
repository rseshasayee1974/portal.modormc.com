<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeighbridgeAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $errorDetails;
    public $aiDiagnosis;
    public $user;
    public $plant;
    public $timestamp;

    /**
     * Create a new message instance.
     */
    public function __construct($errorDetails = [], $aiDiagnosis = null, $user = null, $plant = null)
    {
        $this->errorDetails = $errorDetails;
        $this->aiDiagnosis = $aiDiagnosis;
        $this->user = $user;
        $this->plant = $plant;
        $this->timestamp = now()->toDayDateTimeString();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ Alert: Weighbridge Connectivity Problem',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.weighbridge_alert',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
