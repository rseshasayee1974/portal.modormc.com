<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 15:15:46 Asia/Calcutta (UTC+05:30)
*/

namespace App\Mail;

use App\Models\SalesOrder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SalesOrderCreatedMail extends Mailable
{
    public function __construct(protected SalesOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@modormc.com', 'ModoRMC'),
            subject: 'Sales order created | ' . ($this->order->full_number ?: '#' . $this->order->getKey()),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sales_order_created', with: [
            'reference' => $this->order->full_number ?: '#' . $this->order->getKey(),
            'plant' => $this->order->plant?->name,
            'customer' => $this->order->customer?->legal_name,
            'site' => $this->order->site?->name,
            'mix' => $this->order->mixDesign?->design_name,
            'quantity' => $this->order->total_qty,
            'salesOrdersUrl' => route('salesorders.index'),
        ]);
    }
}
