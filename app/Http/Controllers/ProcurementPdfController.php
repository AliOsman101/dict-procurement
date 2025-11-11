<?php
namespace App\Http\Controllers;

use App\Models\Procurement;
use App\Models\DefaultApprover;
use App\Models\RfqResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class ProcurementPdfController extends Controller
{
    public function generatePrPdf($procurementId)
    {
        $procurement = Procurement::where('id', $procurementId)
            ->where('module', 'purchase_request')
            ->with([
                'procurementItems',
                'approvals' => fn ($query) => $query->where('module', 'purchase_request')->with('employee')->orderBy('sequence'),
                'requester',
                'fundCluster',
                'category',
                'employees',
            ])
            ->firstOrFail();

        $pdf = Pdf::loadView('procurements.pr', [
            'procurement' => $procurement,
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

        $approver = DefaultApprover::where('module', 'request_for_quotation')
            ->where('office_section', $procurement->office_section)
            ->first();
        $approval = $approver ? $procurement->approvals()
            ->where('module', 'request_for_quotation')
            ->where('employee_id', $approver->employee_id)
            ->with('employee')
            ->first() : null;

        $pdf = Pdf::loadView('procurements.rfq', [
            'procurement' => $procurement,
            'approval' => $approval,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("RFQ-{$procurement->procurement_id}.pdf");
    }

    public function generateRfqResponsePdf($rfqResponseId)
    {
        $rfqResponse = RfqResponse::where('id', $rfqResponseId)
            ->with([
                'procurement' => fn ($query) => $query->with([
                    'parent.children' => fn ($q) => $q->where('module', 'purchase_request')->with('procurementItems'),
                    'fundCluster',
                    'category',
                    'requester',
                ]),
                'quotes.procurementItem',
                'supplier',
            ])
            ->firstOrFail();

        $procurement = $rfqResponse->procurement;
        $approver = DefaultApprover::where('module', 'request_for_quotation')
            ->where('office_section', $procurement->office_section)
            ->first();
        $approval = $approver ? $procurement->approvals()
            ->where('module', 'request_for_quotation')
            ->where('employee_id', $approver->employee_id)
            ->with('employee')
            ->first() : null;

        $pdf = Pdf::loadView('procurements.rfq-response', [
            'procurement' => $procurement,
            'rfqResponse' => $rfqResponse,
            'approval' => $approval,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("RFQ-Response-{$procurement->procurement_id}-{$rfqResponse->id}.pdf");
    }

    public function generateAoqPdf($parentId)
    {
        // Find the AOQ record using parent_id, not direct id
        $aoq = Procurement::where('parent_id', $parentId)
            ->where('module', 'abstract_of_quotation')
            ->with([
                'approvals' => fn ($query) => $query->where('module', 'abstract_of_quotation')->with('employee')->orderBy('sequence'),
                'fundCluster',
                'category',
                'parent',
            ])
            ->firstOrFail();

        // Load PR items and requester
        $pr = Procurement::where('parent_id', $parentId)
            ->where('module', 'purchase_request')
            ->with(['procurementItems', 'requester'])
            ->first();

        // Load RFQ and responses
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

    public function generateBacPdf($procurementId)
    {
        $procurement = Procurement::where('id', $procurementId)
            ->where('module', 'bac_resolution_recommending_award')
            ->with([
                'procurementItems',
                'approvals' => fn ($query) => $query->where('module', 'bac_resolution_recommending_award')->with('employee')->orderBy('sequence'),
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
            ->with([
                'approvals' => fn ($query) => $query->where('module', 'purchase_order')->with('employee')->orderBy('sequence'),
                'fundCluster',
                'category',
                'parent',
            ])
            ->firstOrFail();

        $pdf = Pdf::loadView('procurements.po', [
            'procurement' => $po,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("PO-{$po->procurement_id}.pdf");
    }
}