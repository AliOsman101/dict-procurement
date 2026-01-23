<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order</title>
    <style>
        body { 
            font-family: 'DejaVu Sans', 'Arial', sans-serif; 
            font-size: 11px; 
            margin: 20px; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-left: 10px; 
        }
        .highlight-yellow { 
            background-color: #ffffc8; 
            font-weight: bold; 
            text-align: center; 
            font-family: "Arial Black", sans-serif; 
        }
        .no-border td, .no-border th { 
            border: none !important; 
        }
        td, th { 
            border: 1px solid black; 
            padding: 4px; 
            vertical-align: middle; 
        }
        .s3 { 
            text-align: center; 
            font-weight: bold; 
            font-size: 10pt; 
            background-color: #ffffc8; 
        }
        .s4 { 
            border-bottom: 1px solid #000; 
        }
        th { 
            background-color: #f2f2f2; 
            text-align: center; 
        }
        .center { 
            text-align: center; 
        }
        .right { 
            text-align: right; 
        }
        
        /* Signature container - ADAPTIVE sizing that NEVER disrupts layout */
        .signature-container {
            width: 100%;
            height: 35px;
            text-align: center;
            overflow: hidden;
            margin: 0 0 5px 0;
            padding: 0;
            line-height: 35px;
        }
        
        .signature-img {
            max-width: 110px;
            max-height: 35px;
            width: auto;
            height: auto;
            display: inline-block;
            vertical-align: middle;
            mix-blend-mode: multiply;
            object-fit: contain;
        }
    </style>
</head>
<body>

    <!-- Signatories -->
@php
use App\Models\DefaultApprover;
use Illuminate\Support\Facades\Crypt;

// Get all default PO approvers
$budgetOfficer     = $defaultApprovers->firstWhere('sequence', 1);
$accountant        = $defaultApprovers->firstWhere('sequence', 2);
$regionalDirector  = $defaultApprovers->firstWhere('sequence', 3);
@endphp



@php
if (!function_exists('po_signatory_block')) {
    function po_signatory_block($approver, $fallbackLabel) {

        if (!$approver) {
            return '
                <div class="signature-container"></div>
                <span class="underline">_______________________</span><br>
                <i>' . $fallbackLabel . '</i>
            ';
        }

        $employee = $approver->employee;
        $name = strtoupper($employee->full_name ?? 'NOT SET');
        $designation = $approver->designation ?? $fallbackLabel;

        $signature = null;
        if ($employee?->certificate?->signature_image_path) {
            try {
                $signature = Crypt::decryptString($employee->certificate->signature_image_path);
            } catch (\Exception $e) {
                $signature = null;
            }
        }

        return '
            <div class="signature-container">
                ' . ($signature ? '<img class="signature-img" src="data:image/png;base64,' . $signature . '">' : '') . '
            </div>
            <span class="underline">' . $name . '</span><br>
            <i>' . $designation . '</i>
        ';
    }
}
@endphp


    

    <!-- Header with Logo and Text -->
    <div style="text-align:center; margin:0; padding:0;">

        <!-- Container for Logo and Text Side by Side -->
        <div style="display:inline-block; text-align:left; vertical-align:top;">
            
            <!-- Logo and Text in Flexbox -->
            <div style="display:table; margin:0 auto;">
                
                <!-- DICT Logo -->
                <div style="display:table-cell; vertical-align:middle; padding-right:12px;">
                    <img src="{{ public_path('images/dict-logo-only.png') }}" 
                         alt="DICT Logo" 
                         style="width:80px; height:80px;">
                </div>

                <!-- Republic and Department Text -->
                <div style="display:table-cell; text-align:center; vertical-align:middle; font-family:'Arial', sans-serif;">
                    <div style="font-size:10px; color: #1055C9; font-weight:bold; letter-spacing:0.5px;">
                        REPUBLIC OF THE PHILIPPINES
                    </div>

                    <!-- Horizontal line -->
                    <div style="border-top:1px solid black; margin:2px 0;"></div>
                    <div style="font-size:11px; color: #05339C; text-align:center; font-weight:bold; letter-spacing:0.3px; line-height:1.3;">
                        DEPARTMENT OF INFORMATION AND<br>COMMUNICATIONS TECHNOLOGY
                    </div>
                </div>
            </div>
        </div>

        <!-- Address (Centered Below) -->
        <p style="margin:8px 0 0 0; font-size:8px; margin-bottom: 10px; text-align:center;">
            <strong>CORDILLERA ADMINISTRATIVE REGION</strong><br>
            DICT Compound, Polo Field, Saint Joseph Village, Baguio City 2600
        </p>
    </div>

    <!-- Title -->
    <table class="no-border">
        <tr style="height: 25px;">
            <td class="s3" colspan="26">PURCHASE ORDER</td>
        </tr>
        <tr style="height: 8px;">
            <td class="s4" colspan="26"></td>
        </tr>
    </table>

@php
// CLEAN INITIALIZATION — prevents undefined variable

$winningSupplier  = $supplier->business_name ?? 'Unknown Supplier';
$supplierAddress  = $supplier->business_address ?? '';
$supplierTin      = $supplier->tin ?? '';
$supplierVat      = $supplier->vat ?? false;


@endphp

@php
// Get parent and related procurements
$parent = $procurement->parent;

$rfq = $parent ? $parent->children()->where('module', 'request_for_quotation')->first() : null;
$pr  = $parent ? $parent->children()->where('module', 'purchase_request')->first() : null;




// Format TIN
$tinDisplay = $supplierTin 
    ? (($supplierVat ? 'VAT ' : 'NON-VAT ') . $supplierTin)
    : '';

// Delivery Term
$deliveryTerm = 'Not set';
if ($rfq) {
    if ($rfq->delivery_mode === 'days' && $rfq->delivery_value) {
        $deliveryTerm = "within {$rfq->delivery_value} calendar days upon receipt of Purchase Order";
    } elseif ($rfq->delivery_mode === 'date' && $rfq->delivery_value) {
        $deliveryTerm = \Carbon\Carbon::parse($rfq->delivery_value)->format('F j, Y');
    }
}

// Procurement Type
$procurementType = $procurement->procurement_type === 'small_value_procurement'
    ? 'Small Value Procurement'
    : 'Public Bidding';

// PR Items
$isLot = $pr && $pr->basis === 'lot';

// Approvals
$poApprovals = $procurement->approvals()
    ->where('module', 'purchase_order')
    ->orderBy('sequence')
    ->with('employee.certificate')
    ->get();

$regionalDirectorApproval = $poApprovals->where('designation', 'Regional Director')->first();
$budgetOfficerApproval    = $poApprovals->where('designation', 'Budget Officer')->first();
@endphp



@php
// Use injected items (MAIL / VIEW CONTEXT)
// Only compute fallback if NOT provided
$totalContractAmount = 0;

if (!isset($items)) {
    $items = collect();
}

foreach ($items as $item) {
    $totalContractAmount += $item->total_cost ?? 0;
}
@endphp




    <!-- Supplier & PO Details -->
    <table style="width:100%; border-collapse: collapse; font-size:11px;">
        <!-- Row 1: Supplier + PO No. -->
        <tr style="height:20px;">
            <td style="border:1px solid black; padding:2px 4px; width:55%; vertical-align:middle;">
                Supplier: <b>{{ strtoupper($winningSupplier) }}</b>
            </td>
            <td style="border:1px solid black; padding:2px 4px; width:45%; vertical-align:middle;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span>PO No.:</span>
                    <span style="color:blue; font-weight:bold;">{{ $procurement->procurement_id }}</span>
                </div>
            </td>
        </tr>
        <!-- Row 2: Address + Date -->
        <tr style="height:20px;">
            <td style="border:1px solid black; padding:2px 4px; vertical-align:middle;">
                Address: <b>{{ $supplierAddress }}</b>
            </td>
            <td style="border:1px solid black; padding:2px 4px; vertical-align:middle;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Date:</span>
                    <span style="color:blue; font-weight:bold;">
                        {{ $procurement->po_date 
                            ? \Carbon\Carbon::parse($procurement->po_date)->format('d-M-Y') 
                            : \Carbon\Carbon::now()->format('d-M-Y') 
                        }}
                    </span>
                </div>
            </td>
        </tr>
        <!-- Row 3: TIN + Mode of Procurement -->
        <tr style="height:20px;">
            <td style="border:1px solid black; padding:2px 4px; vertical-align:middle;">
                TIN: <b>{{ $tinDisplay }}</b>
            </td>
            <td style="border:1px solid black; padding:2px 4px; vertical-align:middle;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Mode of Procurement:</span>
                    <span style="color:blue; font-weight:bold;">{{ $procurementType }}</span>
                </div>
            </td>
        </tr>
        <!-- Row 4: Intro Statement -->
        <tr style="height:20px;">
            <td colspan="2" style="border:1px solid black; padding:2px 4px; font-style:italic;">
                Gentlemen: Please furnish this Office the following articles subject to the terms and conditions contained herein:
            </td>
        </tr>
    </table>

    <!-- Place & Date of Delivery + Terms -->
    <table style="width:100%; border-collapse: collapse; font-size:11px;">
        <!-- Place of Delivery + Delivery Term -->
        <tr style="height:20px;">
            <td style="border:1px solid black; padding:0px 4px; width:38%; vertical-align:middle;">
                Place of Delivery: <b>{{ $procurement->place_of_delivery ?? 'Not set' }}</b>
            </td>
            <td style="border:1px solid black; padding:0px 4px; width:62%; vertical-align:middle;">
                Delivery Term: <b>{{ $deliveryTerm }}</b>
            </td>
        </tr>

        <!-- Date of Delivery + Payment Term -->
        <tr style="height:20px;">
            <td style="border:1px solid black; padding:0px 4px; width:35%; vertical-align:middle;">
                Date of Delivery: <b>
                    {{ $procurement->date_of_delivery 
                        ? \Carbon\Carbon::parse($procurement->date_of_delivery)->format('F j, Y') 
                        : 'Not set' 
                    }}
                </b>
            </td>
            <td style="border:1px solid black; padding:0px 4px; width:65%; vertical-align:middle;">
                Payment Term: <b>{{ $procurement->payment_term ?? 'Not set' }}</b>
            </td>
        </tr>
    </table>



<table class="items-table" style="width:100%; font-size:11px;">
    <thead>
        <tr>
            <th style="width:8%;">{{ $isLot ? 'Lot No.' : 'Stock Property No.' }}</th>
            <th style="width:10%;">Unit</th>
            <th style="width:42%;">{{ $isLot ? 'Lot Description' : 'Item Description' }}</th>
            <th style="width:10%;">Quantity</th>
            <th style="width:15%;">Unit Cost</th>
            <th style="width:15%;">Total Cost</th>
        </tr>
    </thead>

    <tbody>
        @forelse ($items as $item)
        <tr>
            <td class="center">{{ $item->sort }}</td>
            <td class="center">{{ $item->unit }}</td>
            <td>{{ $item->item_description }}</td>
            <td class="center">{{ $item->quantity }}</td>
            <td class="right">&#8369;{{ number_format($item->unit_cost, 2) }}</td>
            <td class="right">&#8369;{{ number_format($item->total_cost, 2) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="6" class="center">No awarded items</td>
        </tr>
        @endforelse

        {{-- Pad rows to 5 (DomPDF safe) --}}
        @for ($i = $items->count(); $i < 5; $i++)
        <tr>
            <td></td><td></td><td></td><td></td><td></td><td></td>
        </tr>
        @endfor
    </tbody>
</table>


    <!-- Purpose -->
    <table style="width:100%;">
        <tr>
            <td class="highlight-yellow">
                <i>Purpose: Procurement of {{ $procurement->title ?? 'supplies for the replenishment of stocks used for the ICT Industry Development Programs' }}</i>
            </td>
        </tr>
    </table>
    
    <!-- Total Amount and Penalty Clause -->
    @php
        $totalAmountInWords = \App\Helpers\NumberToWords::transformNumber('en', $totalContractAmount);
    @endphp
    <table style="width:100%; border-collapse: collapse; font-size:11px;">
        <tr>
            <td style="border:1px solid black; padding:4px; text-align:left;" colspan="2">
                (Total Amount in Words): <b>{{ strtoupper($totalAmountInWords) }} PESOS ONLY</b>
            </td>
            <td style="border:1px solid black; padding:4px; text-align:right; font-weight:bold;">
                &#8369;{{ number_format($totalContractAmount, 2) }}
            </td>
        </tr>
        <tr>
            <td colspan="3" style="border-left:1px solid black; border-right:1px solid black; border-bottom:none; border-top:none; padding:4px; font-size:10px; font-style:italic;">
                In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one percent for every day of delay shall be imposed on the undelivered item/s.
            </td>
        </tr>
        <tr>
            <td style="border-left:1px solid black; border-bottom:none; border-top:none; border-right:none; padding:4px; width:50%;">
                Conforme:
            </td>
            <td colspan="2" style="border-right:1px solid black; border-bottom:none; border-top:none; border-left:none; padding:4px; width:50%;">
                Very truly yours,
            </td>
        </tr>
    </table>


<!-- SIGNATORIES -->
<table style="width:100%; border-collapse: collapse; font-size:11px; margin-top:20px;">
    <tr>
        <td style="border-left:1px solid black; border-right:none; border-bottom:none; border-top:none; 
                   padding:30px 10px 5px 10px; text-align:center; width:50%; vertical-align:bottom;">
            __________________________________<br>
            <b>Signature over Printed Name of Supplier</b>
        </td>

        <td style="border-right:1px solid black; border-left:none; border-bottom:none; border-top:none; 
                   padding:10px; text-align:center; width:50%; vertical-align:bottom;">
            {!! po_signatory_block($regionalDirector, 'Regional Director / HOPE') !!}
        </td>
    </tr>

    <tr>
        <td style="border-left:1px solid black; border-right:none; border-bottom:1px solid black; border-top:none; 
                   padding:30px 10px 5px 10px; text-align:center; vertical-align:bottom;">
            __________________________________<br>
            <b>Date</b>
        </td>

        <td style="border-right:1px solid black; border-left:none; border-bottom:1px solid black; border-top:none; padding:30px 10px 5px 10px;">
        </td>
    </tr>
</table>

<!-- FUND CERTIFICATION + ACCOUNTANT -->
<table style="width:100%; border-collapse: collapse; font-size:11px;">
    <tr>
        <td colspan="2" style="border-left:1px solid black; border-right:1px solid black; border-bottom:1px solid black; 
                border-top:none; padding:10px; text-align:center; width:51.8%;">
            {!! po_signatory_block($accountant, 'Accountant') !!}
        </td>

        <td colspan="2" style="border-left:none; border-right:1px solid black; border-bottom:1px solid black; 
                border-top:none; padding:30px 10px 5px 10px; width:48.2%;">
        </td>
    </tr>
</table>

</body>
</html>