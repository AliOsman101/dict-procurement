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

    public function __construct(Procurement $procurement)
    {
        $this->procurement = $procurement;
    }

    public function build()
    {
        return $this->subject('Purchase Order Locked: ' . $this->procurement->procurement_id)
                    ->view('emails.purchase-order-locked'); // FIXED
    }
}
