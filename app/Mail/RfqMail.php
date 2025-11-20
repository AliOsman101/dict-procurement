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

    public function __construct($emailBody, $pdfPath)
    {
        $this->emailBody = $emailBody; // Already processed in the action
        $this->pdfPath = $pdfPath;
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
        return new Content(
            view: 'emails.rfq',
            with: [
                'emailBody' => $this->emailBody,
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