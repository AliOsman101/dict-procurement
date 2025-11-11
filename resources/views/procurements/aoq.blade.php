<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Abstract of Quotation</title>
    <style>
        body { font-family: 'Play', 'DejaVu Sans', sans-serif; font-size: 8pt; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-left: 10px;}
        .no-border td, .no-border th { border: none !important;}
        td, th { border: 1px solid black; padding: 4px; vertical-align: middle; }
        .s3 { text-align: center; font-weight: bold; font-size: 10pt; background-color: #ffffc8; }
        .s5, .s6, .s7, .s8, .s9, .s10, .s11 { padding: 4px; vertical-align: middle;}
        .s7, .s9, .s10 {font-weight: bold; color: #0000ff;}
        .s5, .s8, .s11 {text-align: left;font-weight: bold;border: 1px solid #000;}
        .s6, .s7, .s9, .s10 {text-align: left;border-top: 1px solid #000;border-bottom: 1px solid #000;border-left: none;border-right: none;}
        .s4 {border-bottom: 1px solid #000;}
        .s5, .s8 {border-right: none !important;}
        .s6, .s7, .s9, .s10 {border-left: none !important;}

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .blue { color: #0000FF; font-weight: bold; }
        .header { font-size: 10pt; font-weight: bold; text-align: center; padding: 6px; }
        .no-border td { border: none !important; }
        .remarks { font-style: italic; font-size: 10px; }
        .underline { text-decoration: underline; font-weight: bold; }
        .signatories td { border: none; text-align: center; padding: 20px 5px 5px 5px; }
        .legend { font-size: 10px; }
        .legend .red { color: red; font-style: italic; }
        .legend .highlight { background-color: #ffffc8; font-style: italic; }
    </style>
</head>
<body>

    @php
        // Get PR data
        $prNo = $pr ? $pr->procurement_id : 'Not set';
        $abc = $pr ? $pr->grand_total : 0;
        $procurementType = $aoq->procurement_type === 'small_value_procurement' ? 'Small Value Procurement' : 'Public Bidding';
        $rfqNo = $aoq->procurement_id ?? 'Not set';
        $items = $pr ? $pr->procurementItems : collect();
        $isLot = $pr && $pr->basis === 'lot';
        $categoryName = $aoq->category ? $aoq->category->name : '';
        
        // Calculate supplier totals and get 3 lowest
        $supplierTotals = $rfqResponses->map(function($rfqResponse) use ($aoq) {
            $failedDocs = \App\Models\AoqEvaluation::where('procurement_id', $aoq->id)
                ->where('rfq_response_id', $rfqResponse->id)
                ->where('requirement', 'not like', 'quote_%')
                ->where('status', 'fail')
                ->exists();
            
            $totalQuoted = $rfqResponse->quotes->sum('total_value');
            
            return [
                'rfq_response' => $rfqResponse,
                'total_quoted' => $totalQuoted,
                'disqualified' => $failedDocs,
            ];
        })->sortBy('total_quoted')->values();
        
        // Get only top 3 lowest suppliers
        $suppliers = $supplierTotals->take(3)->pluck('rfq_response');
        $supplierCount = $suppliers->count();
        
        // Prepare eligibility requirements based on category and ABC (matching RFQ logic)
        $eligibilityRequirements = [
            [
                'letter' => 'a)',
                'label' => 'Request for Quotation (RFQ)',
                'required' => true,
                'checked' => true,
                'db_field' => 'original_rfq_document', // Maps to database field
            ],
            [
                'letter' => 'b)',
                'label' => 'Latest Business/Mayor\'s Permit issued by the city or municipality where the principal place of business of the bidder is located',
                'required' => true,
                'checked' => true,
                'db_field' => 'mayors_permit',
            ],
            [
                'letter' => 'c)',
                'label' => 'PhilGEPS Certificate of Registration; or Screenshot of PhilGEPS Registration Information',
                'required' => true,
                'checked' => true,
                'db_field' => 'philgeps_certificate',
            ],
            [
                'letter' => 'd)',
                'label' => 'Professional License/CV (for Consulting Services)',
                'required' => $categoryName == 'Consulting Services',
                'checked' => $categoryName == 'Consulting Services',
                'db_field' => 'professional_license_cv',
            ],
            [
                'letter' => 'e)',
                'label' => 'PCAB License (for Infrastructure Projects)',
                'required' => $categoryName == 'Infrastructure Projects',
                'checked' => $categoryName == 'Infrastructure Projects',
                'db_field' => 'pcab_license',
            ],
            [
                'letter' => 'f)',
                'label' => 'Latest Income/Business Tax Return (for ABCs above Php 500,000.00)',
                'required' => $abc > 500000,
                'checked' => $abc > 500000,
                'db_field' => 'tax_return',
            ],
            [
                'letter' => 'g)',
                'label' => 'Terms and Conditions for Contract of Service (catering) / Technical Specifications and Requirements (printing service)',
                'required' => in_array($categoryName, ['Catering Services', 'Printing Services']),
                'checked' => in_array($categoryName, ['Catering Services', 'Printing Services']),
                'db_field' => 'terms_conditions_tech_specs',
            ],
            [
                'letter' => 'h)',
                'label' => 'Notarized Omnibus Sworn Statement using GPPB-Prescribed Format',
                'required' => true,
                'checked' => true,
                'note' => 'May be submitted before the award of the contract for supplier with a total amount of contract above <b>Php 50,000.00</b>',
                'db_field' => 'omnibus_sworn_statement',
            ],
        ];
        
        // Filter to only show checked requirements
        $eligibilityRequirements = collect($eligibilityRequirements)->filter(fn($req) => $req['checked'])->values()->all();
        
        // Helper function to get evaluation status for a requirement
        function getEvalStatus($rfqResponse, $dbField, $aoqId) {
            // Try multiple possible field name variations
            $possibleFields = [$dbField];
            
            // Add variations for RFQ document
            if ($dbField === 'original_rfq_document') {
                $possibleFields[] = 'rfq_document';
            } elseif ($dbField === 'rfq_document') {
                $possibleFields[] = 'original_rfq_document';
            }
            
            $eval = \App\Models\AoqEvaluation::where('procurement_id', $aoqId)
                ->where('rfq_response_id', $rfqResponse->id)
                ->whereIn('requirement', $possibleFields)
                ->first();
            
            if (!$eval) return ['status' => 'pending', 'remarks' => ''];
            return ['status' => $eval->status, 'remarks' => $eval->remarks ?? ''];
        }
        
        // Check if supplier passed all document evaluations
        function supplierPassed($rfqResponse, $aoqId) {
            $failedDocs = \App\Models\AoqEvaluation::where('procurement_id', $aoqId)
                ->where('rfq_response_id', $rfqResponse->id)
                ->where('requirement', 'not like', 'quote_%')
                ->where('status', 'fail')
                ->exists();
            
            return !$failedDocs;
        }
        
        // Check if quote is above ABC
        function isAboveABC($quote, $item) {
            if (!$quote || !$item) return false;
            return $quote->total_value > $item->total_cost;
        }
    @endphp

    <!-- Header with Logo and Text -->
    <div style="text-align:center; margin:0; padding:0;">
        <div style="display:inline-block; text-align:left; vertical-align:top;">
            <div style="display:table; margin:0 auto;">
                <div style="display:table-cell; vertical-align:middle; padding-right:12px;">
                    <img src="{{ public_path('images/dict-logo-only.png') }}" 
                        alt="DICT Logo" 
                        style="width:80px; height:80px;">
                </div>
                <div style="display:table-cell; text-align:center; vertical-align:middle; font-family:'Arial', sans-serif;">
                    <div style="font-size:10px; color: #1055C9; font-weight:bold; letter-spacing:0.5px;">
                        REPUBLIC OF THE PHILIPPINES
                    </div>
                    <div style="border-top:1px solid black; margin:2px 0;"></div>
                    <div style="font-size:11px; color: #05339C; text-align:center; font-weight:bold; letter-spacing:0.3px; line-height:1.3;">
                        DEPARTMENT OF INFORMATION AND<br>COMMUNICATIONS TECHNOLOGY
                    </div>
                </div>
            </div>
        </div>
        <p style="margin:8px 0 0 0; font-size:8px; margin-bottom: 10px; text-align:center;">
            <strong>CORDILLERA ADMINISTRATIVE REGION</strong><br>
            DICT Compound, Polo Field, Saint Joseph Village, Baguio City 2600
        </p>
    </div>

    <!-- Title -->
    <table class="no-border">
        <tr style="height: 25px;"><td class="s3" colspan="26">ABSTRACT OF QUOTATION</td></tr>
        <tr style="height: 8px;"><td class="s4" colspan="26" margin-top: 0px;></td></tr>
    </table>

    <!-- Details -->
    <table style="border: 1px solid black; border-collapse: collapse; margin-left: 10px; margin-bottom: 15px; margin-top: 15px; font-size: 8pt;">
        <tr style="height: 19px;">
            <td class="s5" colspan="7">Title/ Purpose:</td>
            <td class="s6 blue" colspan="19">{{ $aoq->title }}</td>
        </tr>
        <tr style="height: 19px;">
            <td class="s5" colspan="7">ABC:</td>
            <td class="s7" colspan="4">₱{{ number_format($abc, 2) }}</td>
            <td class="s8" colspan="4">End User:</td>
            <td class="s9" colspan="11">
                {{ $aoq->office_section == 'DICT CAR - Technical Operations Division' ? 'DICT CAR - Technical Operations Division' : 'DICT CAR - Admin and Finance Division' }}
            </td>
        </tr>
        <tr style="height: 19px;">
            <td class="s5" colspan="7">Purchase Request No.:</td>
            <td class="s7" colspan="4">{{ $prNo }}</td>
            <td class="s8" colspan="4">Mode of Procurement:</td>
            <td class="s10" colspan="11">{{ $procurementType }}</td>
        </tr>
        <tr style="height: 19px;">
            <td class="s5" colspan="7">Request for Quotation No.:</td>
            <td class="s7" colspan="4">{{ $rfqNo }}</td>
            <td class="s8" colspan="4">Delivery Period (CD):</td>
            <td class="s9" colspan="11">
                @if($aoq->delivery_mode === 'days' && $aoq->delivery_value)
                    Within {{ $aoq->delivery_value }} calendar days upon receipt of Purchase Order
                @elseif($aoq->delivery_mode === 'date' && $aoq->delivery_value)
                    {{ \Carbon\Carbon::parse($aoq->delivery_value)->format('F j, Y') }}
                @else
                    Not set
                @endif
            </td>
        </tr>
        <tr style="height: 19px;">
            <td class="s11" colspan="26">Date and Time of Bid Opening: <span class="blue">
                @if($aoq->bid_opening_datetime)
                    {{ \Carbon\Carbon::parse($aoq->bid_opening_datetime)->format('F j, Y \a\t g:i A') }}
                @else
                    Not set
                @endif
            </span></td>
        </tr>
    </table>

    <!-- Eligibility + Technical Specs Table -->
    <table style="width:100%; border-collapse: collapse; font-size: 9px;">
        <!-- Top Header -->
        <tr class="bold">
            <td colspan="5" style="border:1px solid black; text-align: center;">NAME OF BIDDER</td>
            @for($i = 0; $i < 3; $i++)
                <td colspan="2" style="border-top:2px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:2px solid black; text-align: center;">
                    @if($i < $supplierCount)
                        {{ $suppliers[$i]->supplier->business_name ?? $suppliers[$i]->business_name ?? 'SUPPLIER ' . ($i + 1) }}
                    @else
                        SUPPLIER {{ $i + 1 }}
                    @endif
                </td>
            @endfor
            <td colspan="2" style="border:1px solid black; text-align: center;">Remarks</td>
        </tr>

        <!-- Eligibility Header -->
        <tr>
            <td colspan="5" style="border:1px solid black; font-weight:bold;">ELIGIBILITY REQUIREMENTS</td>
            @for($i = 0; $i < 3; $i++)
                <td colspan="2" style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:2px solid black;"></td>
            @endfor
            <td colspan="2" style="border:1px solid black;"></td>
        </tr>

        <!-- Eligibility Rows -->
        @foreach($eligibilityRequirements as $req)
            <tr>
                <td style="border:1px solid black;">{{ $req['letter'] }}</td>
                <td colspan="4" style="border:1px solid black;">
                    {{ $req['label'] }}
                </td>
                @for($i = 0; $i < 3; $i++)
                    @php
                        $supplier = $i < $supplierCount ? $suppliers[$i] : null;
                        $evalData = ['status' => 'pending', 'remarks' => ''];
                        
                        if ($supplier && isset($req['db_field'])) {
                            $evalData = getEvalStatus($supplier, $req['db_field'], $aoq->id);
                        }
                        
                        $cellText = '';
                        $cellColor = '';
                        if ($supplier) {
                            if ($evalData['status'] === 'pass') {
                                $prefix = 'PASSED';
                                // Show remarks if available
                                if (!empty($evalData['remarks'])) {
                                    $cellText = $prefix . ', ' . $evalData['remarks'];
                                } else {
                                    $cellText = $prefix;
                                }
                            } elseif ($evalData['status'] === 'fail') {
                                $prefix = 'FAILED';
                                // Show remarks if available
                                if (!empty($evalData['remarks'])) {
                                    $cellText = $prefix . ', ' . $evalData['remarks'];
                                } else {
                                    $cellText = $prefix;
                                }
                                $cellColor = 'color:red;';
                            } elseif ($evalData['status'] === 'pending') {
                                // Show empty for pending
                                $cellText = '';
                            }
                        }
                    @endphp
                    <td colspan="2" style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:2px solid black; {{ $cellColor }}">
                        {{ $cellText }}
                    </td>
                @endfor
                <td colspan="2" style="border:1px solid black; font-style:italic; text-align:center;">
                    @if(isset($req['note']))
                        {!! $req['note'] !!}
                    @endif
                </td>
            </tr>
        @endforeach

        <!-- Remarks -->
        <tr class="bold center">
            <td colspan="5" style="border:1px solid black; text-align:right;">Remarks:</td>
            @for($i = 0; $i < 3; $i++)
                @php
                    $supplier = $i < $supplierCount ? $suppliers[$i] : null;
                    $passed = $supplier ? supplierPassed($supplier, $aoq->id) : false;
                @endphp
                <td colspan="2" style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:2px solid black; {{ !$passed && $supplier ? 'color:red;' : '' }}">
                    @if($supplier)
                        {{ $passed ? 'PASSED' : 'FAILED' }}
                    @endif
                </td>
            @endfor
            <td colspan="2" style="border:1px solid black;"></td>
        </tr>

        <!-- Technical Specs Header -->
        <tr>
            <td colspan="5" style="border:1px solid black; font-weight:bold;">TECHNICAL SPECIFICATIONS</td>
            @for($i = 0; $i < 3; $i++)
                <td colspan="2" style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:2px solid black;"></td>
            @endfor
            <td colspan="2" style="border:1px solid black; font-weight:bold; text-align:center;">LOWEST BID</td>
        </tr>

        <!-- Technical Specs Table Header -->
        <tr class="center bold">
            <td style="border:1px solid black;">No.</td>
            <td style="border:1px solid black;">{{ $isLot ? 'Lot Description' : 'Item Description' }}</td>
            <td style="border:1px solid black;">Qty</td>
            <td style="border:1px solid black;">Unit</td>
            <td style="border:1px solid black;">ABC</td>
            @for($i = 0; $i < 3; $i++)
                <td style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:1px solid black;">Unit Value</td>
                <td style="border-top:1px solid black; border-bottom:1px solid black; border-left:1px solid black; border-right:2px solid black;">Total Value</td>
            @endfor
            <td style="border:1px solid black;">Unit Value</td>
            <td style="border:1px solid black;">Total Value</td>
        </tr>

        <!-- Item Rows -->
        @foreach($items as $index => $item)
            @php
                // Get quotes for this item from all suppliers
                $itemQuotes = [];
                foreach($suppliers as $supplier) {
                    $quote = $supplier->quotes->firstWhere('procurement_item_id', $item->id);
                    $itemQuotes[] = $quote;
                }
                
                // Find lowest bid for this item (among qualified suppliers only)
                $lowestQuote = null;
                $lowestValue = PHP_FLOAT_MAX;
                foreach($itemQuotes as $idx => $quote) {
                    if ($quote && $quote->total_value > 0) {
                        $supplier = $suppliers[$idx];
                        $passed = supplierPassed($supplier, $aoq->id);
                        $aboveABC = isAboveABC($quote, $item);
                                               
                        // Only consider if supplier passed and quote is not above ABC
                        if ($passed && !$aboveABC && $quote->total_value < $lowestValue) {
                            $lowestValue = $quote->total_value;
                            $lowestQuote = ['index' => $idx, 'quote' => $quote];
                        }
                    }
                }
            @endphp
            <tr>
                <td style="border:1px solid black; text-align:center;">{{ $item->sort }}</td>
                <td style="border:1px solid black;">{{ $item->item_description }}</td>
                <td style="border:1px solid black; text-align:center;">{{ $item->quantity }}</td>
                <td style="border:1px solid black; text-align:center;">{{ $item->unit }}</td>
                <td style="border:1px solid black; text-align:right;">{{ number_format($item->unit_cost, 2) }}</td>
                @for($i = 0; $i < 3; $i++)
                    @php
                        $quote = $itemQuotes[$i] ?? null;
                        $supplier = $i < $supplierCount ? $suppliers[$i] : null;
                        $isLowest = $lowestQuote && $lowestQuote['index'] === $i;
                        $bgColor = $isLowest ? 'background-color: #ffff99;' : '';
                        
                        // Check disqualification reasons
                        $textColor = '';
                        if ($quote && $supplier) {
                            $supplierFailed = !supplierPassed($supplier, $aoq->id);
                            $aboveABC = isAboveABC($quote, $item);
                            
                            if ($supplierFailed || $aboveABC) {
                                $textColor = 'color: red;';
                            }
                        }
                        
                        $cellStyle = $bgColor . $textColor;
                    @endphp
                    <td style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:1px solid black; text-align:right; {{ $cellStyle }}">
                        {{ $quote ? number_format($quote->unit_value, 2) : '0.00' }}
                    </td>
                    <td style="border-top:1px solid black; border-bottom:1px solid black; border-left:1px solid black; border-right:2px solid black; text-align:right; {{ $cellStyle }}">
                        {{ $quote ? number_format($quote->total_value, 2) : '0.00' }}
                    </td>
                @endfor
                <td style="border:1px solid black; text-align:right; background-color: #ffffc8;">
                    {{ $lowestQuote ? number_format($lowestQuote['quote']->unit_value, 2) : '0.00' }}
                </td>
                <td style="border:1px solid black; text-align:right; background-color: #ffffc8;">
                    {{ $lowestQuote ? number_format($lowestQuote['quote']->total_value, 2) : '0.00' }}
                </td>
            </tr>
        @endforeach

        <!-- Add blank rows if needed (for visual consistency) -->
        @for($i = $items->count(); $i < 3; $i++)
            <tr>
                <td style="border:1px solid black; height:20px;"></td>
                <td style="border:1px solid black; height:20px;"></td>
                <td style="border:1px solid black; height:20px;"></td>
                <td style="border:1px solid black; height:20px;"></td>
                <td style="border:1px solid black; height:20px;"></td>
                @for($j = 0; $j < 3; $j++)
                    <td style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:1px solid black; height:20px;"></td>
                    <td style="border-top:1px solid black; border-bottom:1px solid black; border-left:1px solid black; border-right:2px solid black; height:20px;"></td>
                @endfor
                <td style="border:1px solid black; height:20px;"></td>
                <td style="border:1px solid black; height:20px;"></td>
            </tr>
        @endfor

        <!-- Total -->
        <tr class="bold">
            <td style="border-top:1px solid black; border-left:1px solid black; border-right:1px solid black;"></td>
            <td style="border-top:1px solid black; border-left:1px solid black; border-right:1px solid black;"></td>
            <td style="border-top:1px solid black; border-left:1px solid black; border-right:1px solid black;"></td>
            <td style="border-top:1px solid black; border-left:1px solid black; border-right:1px solid black;"></td>
            <td style="border-top:1px solid black; border-left:1px solid black; border-right:1px solid black;"></td>
            @for($i = 0; $i < 3; $i++)
                <td style="border-top:1px solid black; border-left:2px solid black; border-right:1px solid black;"></td>
                <td style="border-top:1px solid black; border-left:1px solid black; border-right:2px solid black;"></td>
            @endfor
            <td colspan="2" style="border-top:1px solid black; border-left:1px solid black; border-right:1px solid black; text-align:left; font-weight:bold;">TOTAL</td>
        </tr>

        <!-- Peso sign row -->
        <tr class="bold">
            <td colspan="5" style="border-left:1px solid black; border-right:1px solid black; border-bottom:1px solid black;"></td>
            @for($i = 0; $i < 3; $i++)
                @php
                    $supplier = $i < $supplierCount ? $suppliers[$i] : null;
                    $total = $supplier ? $supplier->quotes->sum('total_value') : 0;
                                        
                    // Check if supplier is disqualified
                    $supplierFailed = $supplier ? !supplierPassed($supplier, $aoq->id) : false;
                    $textColor = $supplierFailed ? 'color: red;' : '';
                @endphp
                <td style="border-left:2px solid black; border-right:1px solid black; border-bottom:2px solid black;"></td>
                <td style="border-left:1px solid black; border-right:2px solid black; border-bottom:2px solid black; text-align:right; {{ $textColor }}">
                    P {{ number_format($total, 2) }}
                </td>
            @endfor
            @php

                // Calculate lowest responsive bid total
                $lowestTotal = PHP_FLOAT_MAX;
                foreach($suppliers as $supplier) {
                    if (supplierPassed($supplier, $aoq->id)) {
                        $supplierTotal = $supplier->quotes->sum('total_value');
                        if ($supplierTotal > 0 && $supplierTotal < $lowestTotal) {
                            $lowestTotal = $supplierTotal;
                        }
                    }
                }
                if ($lowestTotal === PHP_FLOAT_MAX) $lowestTotal = 0;
            @endphp
            <td colspan="2" style="border:1px solid black; text-align:right; background-color: #ffffc8;">
                P {{ number_format($lowestTotal, 2) }}
            </td>
        </tr>
        </table>
   
    <!-- Prepared / Reviewed -->
    <br><br>
    <table class="no-border signatories" style="width:100%; text-align:center;">
        <tr>
            <td style="width:30%; text-align:left; border: none; padding: 5px; vertical-align:top;">
                <b>Prepared by:</b><br><br>
                <div style="text-align:center;">
                    <span class="underline">KAREN D. ABOY</span><br>
                    <i>BAC Secretariat</i>
                </div>
            </td>
            <td style="width:60%; text-align:left; border: none; padding: 5px 50px 0px 0px;vertical-align:top;">
                <b>Legend:</b><br>
                <div class="legend">
                    <span class="red">Red Mark</span> – Disqualified / Above ABC / NA / Did not comply in Statement of Compliance / Did not meet minimum specifications 
                    <br>
                    <span class="highlight">Highlighted</span> – Lowest Bid / Lowest Responsive Bid
                </div>
            </td>
        </tr>
    </table>
    <br><br>

    <table class="no-border" style="width:100%; text-align:center;">
        <tr>
            <td colspan="6" style="text-align:left; border: none; padding: 5px;">
                <b>Reviewed by:</b>
            </td>
        </tr>
        <tr>
            @php
                $bacMembers = $aoq->approvals->whereIn('sequence', [1, 2, 3])->sortBy('sequence');
            @endphp
            @foreach($bacMembers as $approval)
                <td colspan="2" style="width:33.33%; text-align:center; border: none; padding: 10px 5px 5px 5px;">
                    <span class="underline">{{ strtoupper($approval->employee->full_name ?? 'N/A') }}</span><br>
                    <i>{{ $approval->sequence == 3 ? 'Provisional Member' : 'BAC Member' }}</i>
                </td>
            @endforeach
        </tr>
        <tr>
            @php
                $chairpersons = $aoq->approvals->whereIn('sequence', [4, 5])->sortBy('sequence');
            @endphp
            <td style="width:16.67%; border: none;"></td>
            @foreach($chairpersons as $approval)
                <td colspan="2" style="width:33.33%; text-align:center; border: none; padding: 10px 5px 5px 5px;">
                    <span class="underline">{{ strtoupper($approval->employee->full_name ?? 'N/A') }}</span><br>
                    <i>{{ $approval->sequence == 4 ? 'BAC Vice - Chairperson' : 'BAC Chairperson' }}</i>
                </td>
            @endforeach
            <td style="width:16.67%; border: none;"></td>
        </tr>
    </table>

</body>
</html>