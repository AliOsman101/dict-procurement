<?php

namespace App\Mail;

use App\Models\DefaultApprover;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DefaultApproverAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $approver;
    public $roleName;

    /**
     * Create a new message instance.
     */
    public function __construct(DefaultApprover $approver)
    {
        $this->approver = $approver;
        $this->roleName = ucfirst(str_replace('_', ' ', $approver->module));
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('You have been assigned as the Default Approver')
            ->view('emails.default_approver_assigned');
    }
}
