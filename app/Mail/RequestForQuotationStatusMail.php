<?php

namespace App\Mail;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RequestForQuotationStatusMail extends Mailable
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
        return $this->subject("Request for Quotation {$this->status}")
            ->view('emails.request_for_quotation_status');
    }
}
