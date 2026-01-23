<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\RfqDistribution;

class SupplierReceivedRfqMail extends Mailable
{
    use Queueable, SerializesModels;

    public $distribution;

    public function __construct(RfqDistribution $distribution)
    {
        $this->distribution = $distribution;
    }

    public function envelope(): \Illuminate\Mail\Mailables\Envelope
    {
        return new \Illuminate\Mail\Mailables\Envelope(
            subject: 'RFQ Received by Supplier'
        );
    }

    public function content(): \Illuminate\Mail\Mailables\Content
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.rfq.received-notification',
            with: [
                'supplier' => $this->distribution->supplier->business_name,
                'sentAt' => $this->distribution->sent_at,
                'receivedAt' => $this->distribution->received_at,
            ],
        );
    }
}
