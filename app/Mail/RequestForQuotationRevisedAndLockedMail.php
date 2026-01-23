<?php

namespace App\Mail;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RequestForQuotationRevisedAndLockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $procurement;
    public $approvalLink;

    public function __construct(Procurement $procurement)
    {
        $this->procurement = $procurement;
        
        // Generate the proper Filament review URL for RFQ approvers
        $this->approvalLink = route('filament.admin.resources.review-procurements.approver-view-rfq', [
            'record' => $procurement->parent_id
        ]);
    }

    public function build()
    {
        $prChild = $this->procurement->parent
            ?->children()
            ->where('module', 'purchase_request')
            ->first();
        
        $requestedBy = $prChild?->requester?->full_name ?? 'Not set';

        return $this->subject('Revised RFQ Ready for Approval - ' . $this->procurement->procurement_id)
    ->view('emails.rfq_revised')   // ✅ Correct view name
    ->with([
        'procurementId' => $this->procurement->procurement_id,
        'title' => $this->procurement->title,
        'requestedBy' => $requestedBy,
        'approvalLink' => $this->approvalLink,
        'revisor' => auth()->user()->name ?? 'System',
    ]);
    }
}