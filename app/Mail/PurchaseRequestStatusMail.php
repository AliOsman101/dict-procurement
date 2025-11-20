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
    public $remarks;

    public function __construct(Procurement $procurement, $status, $approver, $remarks = null)
    {
        $this->procurement = $procurement;
        $this->status = $status;
        $this->approver = $approver;
        $this->remarks = $remarks;
    }

    public function build()
    {
        return $this->subject("Purchase Request {$this->status}")
            ->view('emails.purchase_request_status');
    }
}