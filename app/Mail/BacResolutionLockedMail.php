<?php

namespace App\Mail;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BacResolutionLockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $procurement;

    /**
     * Create a new message instance.
     */
    public function __construct(Procurement $procurement)
    {
        $this->procurement = $procurement;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'BAC Resolution Locked - ' . $this->procurement->procurement_id,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.bac_resolution_locked',
            with: [
                'procurement' => $this->procurement,
                'bacResolutionNo' => $this->procurement->procurement_id,
                'title' => $this->procurement->title,
                'dateCreated' => $this->procurement->created_at->format('F d, Y'),
            ],
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