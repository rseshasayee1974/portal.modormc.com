<?php

/*
 * Author: Cipher
 * Created: 2026-11-11 12:57:38 Asia/Calcutta (UTC+05:30)
 */

namespace App\Mail;

use App\Models\SupportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupportRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportRequest $supportRequest)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('[MODORMC Support] '.$this->supportRequest->subject)
            ->replyTo($this->supportRequest->email, $this->supportRequest->name)
            ->view('emails.support_request');
    }
}
