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
        // Get parent and related procurements
        $parent = $procurement->parent;
        $aoq = $parent ? $parent->children()->where('module', 'abstract_of_quotation')->first() : null;
        $rfq = $parent ? $parent->children()->where('module', 'request_for_quotation')->first() : null;
        $pr = $parent ? $parent->children()->where('module', 'purchase_request')->first() : null;
        
        // Get winning supplier from AOQ
        $winningSupplier = null;
        $supplierAddress = '';
        $supplierTin = '';
        $supplierVat = false;
        $winnerRfqResponse = null;
        
        if ($aoq) {
            $tieBreakingRecord = \DB::table('aoq_tie_breaking_records')
                ->where('procurement_id', $aoq->id)
                ->first();
            
            if ($tieBreakingRecord) {
                $winnerRfqResponse = \App\Models\RfqResponse::with(['supplier', 'quotes.procurementItem'])
                    ->find($tieBreakingRecord->winner_rfq_response_id);
            } else {
                $winningEvaluation = \App\Models\AoqEvaluation::where('procurement_id', $aoq->id)
                    ->where('lowest_bid', true)
                    ->with(['rfqResponse.supplier', 'rfqResponse.quotes.procurementItem'])
                    ->first();
                
                $winnerRfqResponse = $winningEvaluation?->rfqResponse;
            }
            
            if ($winnerRfqResponse) {
                $supplier = $winnerRfqResponse->supplier;
                $winningSupplier = $supplier?->business_name ?? $winnerRfqResponse->business_name ?? 'Unknown Supplier';
                $supplierAddress = $supplier?->business_address ?? '';
                $supplierTin = $supplier?->tin ?? '';
                $supplierVat = $supplier?->vat ?? false;
            }
        }
        
        // Format TIN with VAT/NON-VAT prefix
        $tinDisplay = '';
        if ($supplierTin) {
            $tinDisplay = ($supplierVat ? 'VAT ' : 'NON-VAT ') . $supplierTin;
        }
        
        // Get delivery term from RFQ
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
        
        // Get items from PR
        $items = $pr ? $pr->items()->orderBy('sort')->get() : collect();
        $isLot = $pr && $pr->basis === 'lot';
        
        // Get ABC (from PR)
        $abc = $pr ? $pr->grand_total : 0;
        
        // Get PO Approvals with Signatures
        $poApprovals = $procurement->approvals()
            ->where('module', 'purchase_order')
            ->orderBy('sequence')
            ->with('employee.certificate')
            ->get();
        
        $regionalDirectorApproval = $poApprovals->where('designation', 'Regional Director')->first();
        $budgetOfficerApproval = $poApprovals->where('designation', 'Budget Officer')->first();
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

    <!-- Items Table -->
    @php
        $totalContractAmount = 0;
        $itemsWithQuotes = [];
        
        if ($winnerRfqResponse && $items->isNotEmpty()) {
            foreach ($items as $item) {
                $quote = $winnerRfqResponse->quotes->firstWhere('procurement_item_id', $item->id);
                $itemsWithQuotes[] = [
                    'item' => $item,
                    'unit_value' => $quote?->unit_value ?? 0,
                    'total_value' => $quote?->total_value ?? 0,
                ];
                $totalContractAmount += $quote?->total_value ?? 0;
            }
        }
    @endphp

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
            @foreach ($itemsWithQuotes as $data)
                @php
                    $item = $data['item'];
                    $unitValue = $data['unit_value'];
                    $totalValue = $data['total_value'];
                @endphp
                <tr>
                    <td class="center">{{ $item->sort }}</td>
                    <td class="center">{{ $item->unit }}</td>
                    <td>{{ $item->item_description }}</td>
                    <td class="center">{{ $item->quantity }}</td>
                    <td class="right">&#8369;{{ number_format($unitValue, 2) }}</td>
                    <td class="right">&#8369;{{ number_format($totalValue, 2) }}</td>
                </tr>
            @endforeach
            @for ($i = count($itemsWithQuotes); $i < 5; $i++)
                <tr>
                    <td class="center"></td>
                    <td class="center"></td>
                    <td></td>
                    <td class="center"></td>
                    <td class="right"></td>
                    <td class="right"></td>
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

    <!-- Signatories -->
    <table style="width:100%; border-collapse: collapse; font-size:11px;">
        <tr>
            <td style="border-left:1px solid black; border-right:none; border-bottom:none; border-top:none; padding:30px 10px 5px 10px; text-align:center; width:50%; vertical-align:bottom;">
                __________________________________<br>
                <b>Signature over Printed Name of Supplier</b>
            </td>
            <td style="border-right:1px solid black; border-left:none; border-bottom:none; border-top:none; padding:10px; text-align:center; width:50%; vertical-align:bottom;">
                @if($regionalDirectorApproval && $regionalDirectorApproval->signature)
                    <div class="signature-container">
                        <img src="data:image/png;base64,{{ $regionalDirectorApproval->signature }}" 
                             class="signature-img"
                             alt="Signature">
                    </div>
                @else
                    <div style="height:35px; margin-bottom:5px;"></div>
                @endif
                <span style="text-decoration:underline;"><b>{{ strtoupper($regionalDirectorApproval?->employee?->full_name ?? 'ENGR. REYNALDO T. SY') }}</b></span><br>
                <i>Regional Director</i>
            </td>
        </tr>
        <tr>
            <td style="border-left:1px solid black; border-right:none; border-bottom:1px solid black; border-top:none; padding:30px 10px 5px 10px; text-align:center; vertical-align:bottom;">
                __________________________________<br>
                <b>Date</b>
            </td>
            <td style="border-right:1px solid black; border-left:none; border-bottom:1px solid black; border-top:none; padding:30px 10px 5px 10px;">
                <!-- Empty cell -->
            </td>
        </tr>
    </table>

    <!-- Fund Cluster + Accountant Signature -->
    <table style="width:100%; border-collapse: collapse; font-size:11px;">
        <!-- Row 1: Fund Cluster + ORS/BURS No. -->
        <tr>
            <td style="border-left:1px solid black; border-top:1px solid black; border-bottom:none; border-right:none; padding:1px;">
                <b>Fund Cluster:</b>
            </td>
            <td style="border-top:1px solid black; border-bottom:1px solid black; border-left:none; border-right:1px solid black; padding:1px; text-align:left;">
                <span style="color:green; font-weight:bold;">{{ $procurement->fundCluster->name ?? 'Regular Agency Fund' }}</span>
            </td>
            <td style="border-top:1px solid black; border-bottom:none; border-left:none; border-right:none; padding:1px;">
                <b>ORS/BURS No.:</b>
            </td>
            <td style="border-right:1px solid black; border-top:1px solid black; border-bottom:1px solid black; border-left:none; padding:1px; text-align:left; width:25%;">
                <span style="color:purple; font-weight:bold;">{{ $procurement->ors_burs_no ?? 'Not set' }}</span>
            </td>
        </tr>

        <!-- Row 2: Funds Available + Date of ORS/BURS -->
        <tr>
            <td style="border-left:1px solid black; border-bottom:none; border-top:none; border-right:none; padding:1px;">
                <b>Funds Available:</b>
            </td>
            <td style="border-bottom:1px solid black; border-top:none; border-left:none; border-right:1px solid black; padding:1px; text-align:left;">
                <span style="color:green; font-weight:bold;">&#8369;{{ number_format($abc, 2) }}</span>
            </td>
            <td style="border-bottom:none; border-top:none; border-left:none; border-right:none; padding:1px;">
                <b>Date of the ORS/BURS:</b>
            </td>
            <td style="border-right:1px solid black; border-bottom:1px solid black; border-top:none; border-left:none; padding:1px; text-align:left;">
                <span style="color:purple; font-weight:bold;">
                    {{ $procurement->ors_burs_date 
                        ? \Carbon\Carbon::parse($procurement->ors_burs_date)->format('F j, Y') 
                        : 'Not set' 
                    }}
                </span>
            </td>
        </tr>

        <!-- Row 3: Amount -->
        <tr>
            <td style="border-left:1px solid black; border-bottom:none; border-top:none; border-right:none; padding:1px;"></td>
            <td style="border-bottom:none; border-top:none; border-left:none; border-right:1px solid black; padding:1px;"></td>
            <td style="border-bottom:none; border-top:none; border-left:none; border-right:none; padding:1px;">
                <b>Amount:</b>
            </td>
            <td style="border-right:1px solid black; border-bottom:1px solid black; border-top:none; border-left:none; padding:1px; text-align:center;">
                <span style="color:purple; font-weight:bold;">&#8369;{{ number_format($totalContractAmount, 2) }}</span>
            </td>
        </tr>

        <!-- Row 4: Accountant Signature -->
        <tr>
            <td colspan="2" style="border-left:1px solid black; border-right:1px solid black; border-bottom:1px solid black; border-top:none; 
                    padding:10px; text-align:center; width:51.8%;">
                @if($budgetOfficerApproval && $budgetOfficerApproval->signature)
                    <div class="signature-container">
                        <img src="data:image/png;base64,{{ $budgetOfficerApproval->signature }}" 
                             class="signature-img"
                             alt="Signature">
                    </div>
                @else
                    <div style="height:35px; margin-bottom:5px;"></div>
                @endif
                <div style="border-bottom:1px solid black; display:inline-block; padding-bottom:2px;">
                    <b>{{ strtoupper($budgetOfficerApproval?->employee?->full_name ?? 'JANNETH G. CABINTA') }}</b>
                </div><br>
                <i>{{ $budgetOfficerApproval?->designation ?? 'Accountant III' }}</i>
            </td>
            <td colspan="2" style="border-left:none; border-right:1px solid black; border-bottom:1px solid black; border-top:none; 
                    padding:30px 10px 5px 10px; width:48.2%;">
                <!-- Empty cell -->
            </td>
        </tr>
    </table>

</body>
</html>