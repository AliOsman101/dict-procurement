<?php

namespace App\Mail;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseRequestStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $procurement;
    public $status;
    public $approver;

    public function __construct(Procurement $procurement, $status, $approver)
    {
        $this->procurement = $procurement;
        $this->status = $status;
        $this->approver = $approver;
    }

    public function build()
    {
        return $this->subject("Purchase Request {$this->status}")
            ->view('emails.purchase_request_status');
    }
}
