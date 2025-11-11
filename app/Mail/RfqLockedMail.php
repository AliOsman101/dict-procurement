<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Procurement;
use App\Filament\Resources\ReviewProcurementResource;

class RfqLockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $rfq;
    public $approvalLink;

    public function __construct(Procurement $rfq)
    {
        $this->rfq = $rfq;

        // ✅ Generate the proper Filament review URL for RFQ approvers
        $this->approvalLink = ReviewProcurementResource::getUrl('view', [
            'record' => $rfq->parent_id,
        ]) . '/rfq';
    }

    public function build()
    {
        return $this->subject('Request for Quotation ' . $this->rfq->procurement_id . ' Ready for Approval')
                    ->view('emails.rfq_locked')
                    ->with([
                        'rfq' => $this->rfq,
                        'approvalLink' => $this->approvalLink,
                    ]);
    }
}
