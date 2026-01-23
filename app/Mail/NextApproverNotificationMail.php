<?php

namespace App\Mail;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NextApproverNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Procurement $procurement;
    public string $nextApproverName;
    public int $sequence;

    /**
     * Create a new message instance.
     */
    public function __construct(Procurement $procurement, string $nextApproverName, int $sequence)
    {
        $this->procurement = $procurement;
        $this->nextApproverName = $nextApproverName;
        $this->sequence = $sequence;
    }

    /**
     * Build the message.
     */
   public function build()
{
    // -----------------------------
    // MAP MODULE TO FILAMENT ROUTE
    // -----------------------------
    $routes = [
    'purchase_request' => 'filament.admin.resources.review-procurements.approver-view-pr',
    'request_for_quotation' => 'filament.admin.resources.review-procurements.approver-view-rfq',
    'abstract_of_quotation' => 'filament.admin.resources.review-procurements.approver-view-aoq',
    'minutes_of_opening' => 'filament.admin.resources.review-procurements.approver-view-mo',
    'bac_resolution_recommending_award' => 'filament.admin.resources.review-procurements.approver-view-bac',
    'purchase_order' => 'filament.admin.resources.review-procurements.approver-view-po',
];

    $routeName = $routes[$this->procurement->module] ?? null;

    // Prevent crash if module has no matching route
    $actionUrl = $routeName 
        ? route($routeName, ['record' => $this->procurement->parent_id])
        : url('/');

    return $this
        ->subject('Approval Required - Procurement #' . $this->procurement->procurement_id)
        ->view('emails.next-approver-notification')
        ->with([
            'procurement' => $this->procurement,
            'nextApproverName' => $this->nextApproverName,
            'sequence' => $this->sequence,
            'actionUrl' => $actionUrl,
        ]);
}
}