<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Procurement;

class PurchaseOrderLockedMail extends Mailable
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
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Purchase Order Locked: ' . $this->procurement->procurement_id)
                    ->markdown('emails.purchase_order_locked');
    }
}
