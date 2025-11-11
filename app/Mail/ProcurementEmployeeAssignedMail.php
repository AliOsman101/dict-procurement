<?php

namespace App\Mail;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProcurementEmployeeAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $procurement;

    public function __construct(Procurement $procurement)
    {
        $this->procurement = $procurement;
    }

    public function build()
    {
        return $this->subject('You have been added to a Procurement')
            ->view('emails.procurement_employee_assigned');
    }
}
