<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Procurement;

class BacStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $procurement;
    public $approver;
    public $status;
    public $remarks;

    public function __construct(Procurement $procurement, $approver, string $status, ?string $remarks = null)
{
    $this->procurement = $procurement;
    $this->approver = $approver;
    $this->status = $status;
    $this->remarks = $remarks;
}

    public function build()
    {
        return $this->subject("BAC Resolution {$this->status}: {$this->procurement->title}")
                    ->view('emails.bac-status');
    }
}
