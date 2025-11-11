<?php
namespace App\Http\Controllers;

use App\Models\Procurement;
use Barryvdh\DomPDF\Facade\Pdf;

class ProcurementPdfController extends Controller
{
    public function generatePrPdf(Procurement $procurement)
    {
        $procurement->load('items', 'creator', 'requester', 'fundCluster', 'category', 'documents');

        $pdf = Pdf::loadView('pdf.purchase_request', [
            'procurement' => $procurement,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("PR-{$procurement->procurement_id}.pdf");
    }

    public function generateRfqPdf(Procurement $procurement)
    {
        $procurement->load('items', 'creator', 'requester', 'fundCluster', 'category', 'documents', 'approvals');

        $pdf = Pdf::loadView('pdf.rfq', [
            'procurement' => $procurement,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("RFQ-{$procurement->procurement_id}.pdf");
    }

    public function generateAoqPdf(Procurement $procurement)
    {
        $procurement->load('items', 'creator', 'requester', 'fundCluster', 'category', 'documents', 'approvals');

        $pdf = Pdf::loadView('pdf.aoq', [
            'procurement' => $procurement,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("AOQ-{$procurement->procurement_id}.pdf");
    }

    public function generateBacPdf(Procurement $procurement)
    {
        $procurement->load('items', 'creator', 'requester', 'fundCluster', 'category', 'documents', 'approvals');

        $pdf = Pdf::loadView('pdf.bac', [
            'procurement' => $procurement,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("BAC-{$procurement->procurement_id}.pdf");
    }

    public function generatePoPdf(Procurement $procurement)
    {
        $procurement->load('items', 'creator', 'requester', 'fundCluster', 'category', 'documents', 'approvals');

        $pdf = Pdf::loadView('pdf.po', [
            'procurement' => $procurement,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream("PO-{$procurement->procurement_id}.pdf");
    }
}