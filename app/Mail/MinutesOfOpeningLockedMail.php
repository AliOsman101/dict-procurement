<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Procurement;
use Carbon\Carbon;

class MinutesOfOpeningLockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $procurement;
    public $moNumber;
    public $title;
    public $dateCreated;

    public function __construct(Procurement $procurement)
    {
        $this->procurement = $procurement;
        $this->moNumber = $procurement->procurement_id;
        $this->title     = $procurement->title;
        $this->dateCreated = Carbon::parse($procurement->created_at)->format('F j, Y');
    }

    public function build()
    {
        return $this->subject("Minutes of Opening Locked: {$this->title}")
                    ->view('emails.minutes-of-opening-locked');
    }
}
