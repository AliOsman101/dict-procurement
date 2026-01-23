<?php

namespace App\Http\Controllers;

use App\Models\Procurement;
use App\Models\DefaultApprover;
use App\Models\RfqResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\AoqEvaluation;

class ProcurementPdfController extends Controller
{
    public function generatePrPdf($procurementId)
{
    $procurement = Procurement::where('id', $procurementId)
        ->where('module', 'purchase_request')
        ->with([
            'procurementItems',
            'approvals' => fn ($query) => $query
                ->where('module', 'purchase_request')
                ->with('employee.certificate')
                ->orderBy('sequence'),
            'requester',
            'fundCluster',
            'category',
            'employees',
        ])
        ->firstOrFail();

    // 🔥 FETCH DEFAULT APPROVERS (dynamic)
    $approvers = DefaultApprover::where('module', 'purchase_request')
        ->orderBy('sequence')
        ->with('employee')
        ->get();

    $firstApprover = $approvers->firstWhere('sequence', 1);
    $secondApprover = $approvers->firstWhere('sequence', 2);

    // 🔥 PASS APPROVERS TO PDF
    $pdf = Pdf::loadView('procurements.pr', [
        'procurement' => $procurement,
        'firstApprover' => $firstApprover,
        'secondApprover' => $secondApprover,
    ])->setPaper('A4', 'portrait');

    return $pdf->stream("PR-{$procurement->procurement_id}.pdf");
}


    public function generateRfqPdf($procurementId)
{
    $procurement = Procurement::where('parent_id', $procurementId)
        ->where('module', 'request_for_quotation')
        ->with([
            'requester',
            'parent.requester',
            'parent.children' => fn ($query) => $query->where('module', 'purchase_request')->with('procurementItems'),
            'fundCluster',
            'category',
            'employees',
        ])
        ->firstOrFail();

    // 👉 GET RFQ APPROVER FROM DEFAULT APPROVERS
    $officeSection = $procurement->office_section;

    $rfqApprover = DefaultApprover::where('module', 'request_for_quotation')
        ->where('office_section', $officeSection)
        ->with(['employee', 'employee.certificate'])
        ->first();

    return Pdf::loadView('procurements.rfq', [
        'procurement' => $procurement,
        'rfqApprover' => $rfqApprover,
    ])->setPaper('A4', 'portrait')
      ->stream("RFQ-{$procurement->procurement_id}.pdf");
}


    public function generateRfqResponsePdf($rfqResponseId)
    {
        $rfqResponse = RfqResponse::where('id', $rfqResponseId)
            ->with([
                'procurement' => fn ($query) => $query->with([
                    'parent.children' => fn ($q) => $q->where('module', 'purchase_request')->with('procurementItems'),
                    'approvals' => fn ($q) => $q
                        ->where('module', 'request_for_quotation')
                        ->with('employee.certificate') // ← Load certificate!
                        ->orderBy('sequence'),
                    'fundCluster',
                    'category',
                    'requester',
                ]),
                'quotes.procurementItem',
                'supplier',
            ])
            ->firstOrFail();

        $procurement = $rfqResponse->procurement;

        $pdf = Pdf::loadView('procurements.rfq-response', [
            'procurement' => $procurement,
            'rfqResponse' => $rfqResponse,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("RFQ-Response-{$procurement->procurement_id}-{$rfqResponse->id}.pdf");
    }

    public function generateAoqPdf($parentId)
    {
        $aoq = Procurement::where('parent_id', $parentId)
            ->where('module', 'abstract_of_quotation')
            ->with([
                'approvals' => fn ($query) => $query
                    ->where('module', 'abstract_of_quotation')
                    ->with('employee.certificate') // ← Load certificate!
                    ->orderBy('sequence'),
                'fundCluster',
                'category',
                'parent',
            ])
            ->firstOrFail();

        $pr = Procurement::where('parent_id', $parentId)
            ->where('module', 'purchase_request')
            ->with(['procurementItems', 'requester'])
            ->first();

        $rfq = Procurement::where('parent_id', $parentId)
            ->where('module', 'request_for_quotation')
            ->first();

        $rfqResponses = collect();
        if ($rfq) {
            $rfqResponses = RfqResponse::where('procurement_id', $rfq->id)
                ->with([
                    'supplier',
                    'quotes.procurementItem',
                    'aoqEvaluations' => fn ($query) => $query->where('procurement_id', $aoq->id),
                ])
                ->get();
        }

        $pdf = Pdf::loadView('procurements.aoq', [
            'aoq' => $aoq,
            'pr' => $pr,
            'rfqResponses' => $rfqResponses,
        ])->setPaper('A4', 'landscape');

        return $pdf->stream("AOQ-{$aoq->procurement_id}.pdf");
    }

    public function generateMoPdf($parentId)
    {
        // Load the Minutes of Opening record
        $mo = Procurement::where('parent_id', $parentId)
            ->where('module', 'minutes_of_opening')
            ->with([
                'approvals' => fn ($query) => $query
                    ->where('module', 'minutes_of_opening')
                    ->with('employee.certificate')
                    ->orderBy('sequence'),
                'fundCluster',
                'category',
                'parent',
            ])
            ->firstOrFail();

        // Load PR (for title, ABC, items, basis)
        $pr = Procurement::where('parent_id', $parentId)
            ->where('module', 'purchase_request')
            ->with(['procurementItems', 'requester'])
            ->firstOrFail();

        // Load AOQ (for bid_opening_datetime)
        $aoq = Procurement::where('parent_id', $parentId)
            ->where('module', 'abstract_of_quotation')
            ->firstOrFail();

        // Load RFQ (for delivery period, procurement_id)
        $rfq = Procurement::where('parent_id', $parentId)
            ->where('module', 'request_for_quotation')
            ->firstOrFail();

        // Load all RFQ responses (bidders) with needed relations
        $rfqResponses = RfqResponse::where('procurement_id', $rfq->id)
            ->with([
                'supplier',
                'quotes.procurementItem',
                'aoqEvaluations' => fn($q) => $q->where('procurement_id', $aoq->id),
            ])
            ->get();

        // Winning suppliers summary (for Lowest Calculated and Responsive Bid)
        $winningSuppliers = AoqEvaluation::where('procurement_id', $aoq->id)
            ->where('lowest_bid', true)
            ->with(['rfqResponse.supplier'])
            ->get()
            ->groupBy('rfq_response_id')
            ->map(function ($group) {
                $response = $group->first()->rfqResponse;
                
                // Get winning items and group consecutive ones
                $items = $group->map(fn($eval) => $eval->requirement_id)
                    ->map(fn($id) => \App\Models\ProcurementItem::find($id)?->sort)
                    ->filter()
                    ->sort()
                    ->values();
                
                // Group consecutive items into ranges
                $groupedItems = [];
                if ($items->isNotEmpty()) {
                    $start = $items[0];
                    $end = $start;
                    
                    for ($i = 1; $i < $items->count(); $i++) {
                        if ($items[$i] == $end + 1) {
                            $end = $items[$i];
                        } else {
                            $groupedItems[] = $start == $end ? "$start" : "$start-$end";
                            $start = $items[$i];
                            $end = $start;
                        }
                    }
                    $groupedItems[] = $start == $end ? "$start" : "$start-$end";
                }
                
                $itemsStr = implode(',', $groupedItems);
                
                $total = $group->sum(function ($eval) {
                    if (!$eval->rfqResponse) {
                        return 0;
                    }
                    $quote = $eval->rfqResponse->quotes()
                        ->where('procurement_item_id', $eval->requirement_id)
                        ->first();
                    return $quote?->total_value ?? 0;
                });

                return [
                    'name' => $response->supplier?->business_name ?? 'Unknown Supplier',
                    'items' => $itemsStr ?: 'None',
                    'total' => $total,
                ];
            })
            ->values()
            ->toArray();

        $pdf = Pdf::loadView('procurements.mo', [
            'mo' => $mo,
            'aoq' => $aoq,
            'pr' => $pr,
            'rfq' => $rfq,
            'rfqResponses' => $rfqResponses,
            'winningSuppliers' => $winningSuppliers,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("MO-{$mo->procurement_id}.pdf");
    }
    
    public function generateBacPdf($procurementId)
    {
        $procurement = Procurement::where('id', $procurementId)
            ->where('module', 'bac_resolution_recommending_award')
            ->with([
                'procurementItems',
                'approvals' => fn ($query) => $query
                    ->where('module', 'bac_resolution_recommending_award')
                    ->with('employee.certificate') // ← Load certificate!
                    ->orderBy('sequence'),
                'requester',
                'fundCluster',
                'category',
                'employees',
            ])
            ->firstOrFail();

        $pdf = Pdf::loadView('procurements.bac', [
            'procurement' => $procurement,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("BAC-{$procurement->procurement_id}.pdf");
    }

    public function generatePoPdf($procurementId)
{
    $po = Procurement::where('id', $procurementId)
        ->where('module', 'purchase_order')
        ->with(['parent'])
        ->firstOrFail();

    $defaultApprovers = DefaultApprover::where('module', 'purchase_order')
        ->with('employee.certificate')
        ->orderBy('sequence')
        ->get();

    $parent = $po->parent;

    // 🔥 FIND AOQ
    $aoq = $parent?->children()
        ->where('module', 'abstract_of_quotation')
        ->first();

    $items = collect();
    $supplier = null;

    if ($aoq) {
        $aoqWinners = AoqEvaluation::where('procurement_id', $aoq->id)
            ->where('lowest_bid', true)
            ->with('rfqResponse.supplier', 'rfqResponse.quotes.procurementItem')
            ->get();

        // ✅ PICK SUPPLIER FROM FIRST WINNER (VIEW PDF CONTEXT)
        $supplier = optional($aoqWinners->first())
            ->rfqResponse
            ->supplier;

        $items = $aoqWinners
            ->unique('requirement')
            ->map(function ($eval) {
                $quote = $eval->winningQuote();
                if (! $quote) return null;

                $item = clone $quote->procurementItem;
                $item->unit_cost  = $quote->unit_value;
                $item->total_cost = $quote->total_value;

                return $item;
            })
            ->filter()
            ->values();
    }

    return Pdf::loadView('procurements.po', [
        'procurement'      => $po,
        'defaultApprovers' => $defaultApprovers,
        'items'            => $items,
        'supplier'         => $supplier, 
    ])
    ->setPaper('A4', 'portrait')
    ->stream("PO-{$po->procurement_id}.pdf");
}
}