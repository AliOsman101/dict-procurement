<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Procurement;
use App\Filament\Resources\ReviewProcurementResource;

class AbstractOfQuotationLockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $aoq;
    public $approvalLink;

    public function __construct(Procurement $aoq)
    {
        $this->aoq = $aoq;

        // ✅ Direct approvers straight to their AOQ page: /admin/review-procurements/{parent_id}/aoq
        $this->approvalLink = ReviewProcurementResource::getUrl('view', [
            'record' => $aoq->parent_id,
        ]) . '/aoq';
    }

    public function build()
    {
        return $this->subject('Abstract of Quotation ' . $this->aoq->procurement_id . ' Ready for Approval')
                    ->view('emails.aoq_locked');
    }
}
