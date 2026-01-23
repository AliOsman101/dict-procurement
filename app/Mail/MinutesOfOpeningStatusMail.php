<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Procurement;

class MinutesOfOpeningStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $procurement;
    public $employee;
    public $status;
    public $remarks;
    public $link;

    public function __construct(Procurement $procurement, $employee, string $status, ?string $remarks = null, ?string $link = null)
    {
        $this->procurement = $procurement;
        $this->employee = $employee;
        $this->status = $status;
        $this->remarks = $remarks;
        $this->link = $link;
    }

    public function build()
    {
        return $this->subject("Minutes of Opening {$this->status}: {$this->procurement->title}")
            ->view('emails.minutes-status');
    }
}
