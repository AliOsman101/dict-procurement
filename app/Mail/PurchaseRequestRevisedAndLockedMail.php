<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Procurement;
use App\Filament\Resources\ReviewProcurementResource;

class PurchaseRequestRevisedAndLockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $procurement;
    public $approvalLink;

    public function __construct(Procurement $procurement)
    {
        $this->procurement = $procurement;

        // Create URL that goes directly to the approver's PR page
        $this->approvalLink = ReviewProcurementResource::getUrl('view', [
            'record' => $procurement->parent_id,
        ]) . '/pr';
    }

    public function build()
    {
        return $this->subject('Purchase Request ' . $this->procurement->procurement_id . ' Revised and Ready for Re-approval')
                    ->view('emails.purchase_request_revised_locked');
    }
}