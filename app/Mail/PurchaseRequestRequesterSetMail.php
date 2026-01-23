<?php

namespace App\Mail;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseRequestRequesterSetMail extends Mailable
{
    use Queueable, SerializesModels;

    public Procurement $procurement;

    public function __construct(Procurement $procurement)
    {
        $this->procurement = $procurement;
    }

    public function build()
    {
        return $this->subject('You Have Been Set as Requester for PR ' . $this->procurement->procurement_id)
            ->view('emails.pr_requester_set')
            ->with([
                'procurement' => $this->procurement,
                'title' => $this->procurement->title,
                'prNumber' => $this->procurement->procurement_id,
                'setBy' => auth()->user()->name ?? 'System',
            ]);
    }
}
