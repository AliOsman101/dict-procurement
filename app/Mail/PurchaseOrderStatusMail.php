<?php

namespace App\Mail;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $procurement;
    public $status;
    public $approver;
    public $remarks;

    /**
     * Create a new message instance.
     */
    public function __construct(Procurement $procurement, string $status, $approver, ?string $remarks = null)
    {
        $this->procurement = $procurement;
        $this->status = $status;
        $this->approver = $approver;
        $this->remarks = $remarks;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Purchase Order ' . ucfirst($this->status) . ' - ' . ($this->procurement->procurement_id ?? 'PO'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.purchase_order_status',
            with: [
                'procurement' => $this->procurement,
                'status' => $this->status,
                'approver' => $this->approver,
                'remarks' => $this->remarks,
            ],
        );
    }

    /**
     * Get attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
