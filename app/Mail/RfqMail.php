<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RfqMail extends Mailable
{
    use Queueable, SerializesModels;

    public $emailBody;
    public $pdfPath;
    public $rfqDistributionId;

    public function __construct($emailBody, $pdfPath, $rfqDistributionId = null)
    {
        $this->emailBody = $emailBody;
        $this->pdfPath = $pdfPath;
        $this->rfqDistributionId = $rfqDistributionId;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address('car.bac@dict.gov.ph', 'DICT CAR Bids and Awards Committee'),
            subject: 'Request for Quotation',
        );
    }

    public function content(): Content
    {
        // Build receipt URL if id present
        $receiveUrl = $this->rfqDistributionId ? route('rfq.receive', $this->rfqDistributionId) : null;

        return new Content(
            view: 'emails.rfq',
            with: [
                'emailBody' => $this->emailBody,
                'receiveUrl' => $receiveUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as('RFQ.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
