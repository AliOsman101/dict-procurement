<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Procurement;
use App\Models\User;

class PpmpUploadedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $procurement;
    public $uploader;
    public $viewLink;

    public function __construct(Procurement $procurement, User $uploader)
    {
        $this->procurement = $procurement;
        $this->uploader = $uploader;

        // ✅ Generate the view link for the email button
        $this->viewLink = route('filament.admin.resources.procurements.view', $procurement->id);
    }

    public function build()
    {
        return $this->subject('PPMP Uploaded for ' . ($this->procurement->title ?? 'Procurement'))
                    ->view('emails.ppmp_uploaded')
                    ->with([
                        'procurement' => $this->procurement,
                        'uploader' => $this->uploader,
                        'viewLink' => $this->viewLink, // ✅ pass it to the Blade
                    ]);
    }
}
