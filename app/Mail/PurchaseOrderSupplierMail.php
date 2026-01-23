<?php

namespace App\Mail;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrderSupplierMail extends Mailable
{
    use Queueable, SerializesModels;

    public Procurement $po;
    public $supplier;
    public $items; // 👈 awarded items only

    public function __construct(Procurement $po, $supplier, $items)
    {
        $this->po = $po;
        $this->supplier = $supplier;
        $this->items = $items;
    }

    public function build()
    {
        $defaultApprovers = \App\Models\DefaultApprover::where('module', 'purchase_order')
            ->with('employee.certificate')
            ->orderBy('sequence')
            ->get();

        $pdf = Pdf::loadView('procurements.po', [
            'procurement' => $this->po,
            'supplier' => $this->supplier,
            'items' => $this->items, // ✅ override items
            'defaultApprovers' => $defaultApprovers,
        ]);

        return $this->subject('Purchase Order No. ' . $this->po->procurement_id)
            ->view('emails.purchase-order-supplier')
            ->attachData(
                $pdf->output(),
                'PO-' . $this->po->procurement_id . '-' . $this->supplier->id . '.pdf',
                ['mime' => 'application/pdf']
            );
    }
}
