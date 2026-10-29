<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:21:49 Asia/Calcutta (UTC+05:30)
*/

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MachineDocumentExpiryMail extends Mailable
{
    public function __construct(public string $plantName, public string $checkedDate, public array $documents) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Machine Document Expiry Reminders - '.$this->plantName);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.machine_document_expiry');
    }
}
