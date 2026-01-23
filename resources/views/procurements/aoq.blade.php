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
        .signature-container {
            width: 100%; height: 25px; text-align: center; overflow: hidden;
            margin: 0; padding: 0; line-height: 25px;
        }
        .signature-img {
            max-width: 80px; max-height: 25px; width: auto; height: auto;
            display: inline-block; vertical-align: middle; mix-blend-mode: multiply;
            object-fit: contain;
        }
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

    $suppliers = $supplierTotals->take(3)->pluck('rfq_response');
    $supplierCount = $suppliers->count();

    // Eligibility requirements
    $eligibilityRequirements = [
        ['letter'=>'a)','label'=>'Request for Quotation (RFQ)','required'=>true,'checked'=>true,'db_field'=>'original_rfq_document'],
        ['letter'=>'b)','label'=>'Latest Business/Mayor\'s Permit issued by the city or municipality where the principal place of business of the bidder is located','required'=>true,'checked'=>true,'db_field'=>'mayors_permit'],
        ['letter'=>'c)','label'=>'PhilGEPS Certificate of Registration; or Screenshot of PhilGEPS Registration Information','required'=>true,'checked'=>true,'db_field'=>'philgeps_certificate'],
        ['letter'=>'d)','label'=>'Professional License/CV (for Consulting Services)','required'=>$categoryName=='Consulting Services','checked'=>$categoryName=='Consulting Services','db_field'=>'professional_license_cv'],
        ['letter'=>'e)','label'=>'PCAB License (for Infrastructure Projects)','required'=>$categoryName=='Infrastructure Projects','checked'=>$categoryName=='Infrastructure Projects','db_field'=>'pcab_license'],
        ['letter'=>'f)','label'=>'Latest Income/Business Tax Return (for ABCs above Php 500,000.00)','required'=>$abc>500000,'checked'=>$abc>500000,'db_field'=>'tax_return'],
        ['letter'=>'g)','label'=>'Terms and Conditions for Contract of Service (catering) / Technical Specifications and Requirements (printing service)','required'=>in_array($categoryName,['Catering Services','Printing Services']),'checked'=>in_array($categoryName,['Catering Services','Printing Services']),'db_field'=>'terms_conditions_tech_specs'],
        ['letter'=>'h)','label'=>'Notarized Omnibus Sworn Statement using GPPB-Prescribed Format','required'=>true,'checked'=>true,'note'=>'May be submitted before the award of the contract for supplier with a total amount of contract above <b>Php 50,000.00</b>','db_field'=>'omnibus_sworn_statement'],
    ];
    $eligibilityRequirements = collect($eligibilityRequirements)->filter(fn($req)=>$req['checked'])->values()->all();

    // Helper functions
    function getEvalStatus($rfqResponse, $dbField, $aoqId) {
        $possibleFields = [$dbField];
        if ($dbField === 'original_rfq_document') $possibleFields[] = 'rfq_document';
        elseif ($dbField === 'rfq_document') $possibleFields[] = 'original_rfq_document';

        $eval = \App\Models\AoqEvaluation::where('procurement_id',$aoqId)
            ->where('rfq_response_id',$rfqResponse->id)
            ->whereIn('requirement',$possibleFields)->first();

        return $eval ? ['status'=>$eval->status,'remarks'=>$eval->remarks??''] : ['status'=>'pending','remarks'=>''];
    }
    
    function supplierPassed($rfqResponse, $aoqId) {
        return !\App\Models\AoqEvaluation::where('procurement_id',$aoqId)
            ->where('rfq_response_id',$rfqResponse->id)
            ->where('requirement','not like','quote_%')
            ->where('status','fail')->exists();
    }
    
    function isAboveABC($quote, $item) {
        return $quote && $item && $quote->total_value > $item->total_cost;
    }

    // NEW: Get winning bid evaluation for an item from AoqEvaluation table
    function getWinningBidForItem($itemId, $aoqId, $allRfqResponses) {
        $winningEval = \App\Models\AoqEvaluation::where('procurement_id', $aoqId)
            ->where('requirement', 'quote_' . $itemId)
            ->where('lowest_bid', true)
            ->first();
        
        if (!$winningEval) {
            return null;
        }

        // Find the RFQ response and its quote
        $winningResponse = $allRfqResponses->firstWhere('id', $winningEval->rfq_response_id);
        if (!$winningResponse) {
            return null;
        }

        $winningQuote = $winningResponse->quotes->firstWhere('procurement_item_id', $itemId);
        
        return $winningQuote ? [
            'quote' => $winningQuote,
            'response' => $winningResponse
        ] : null;
    }

    // NEW: Check if a specific quote is marked as winning bid in evaluations
    function isWinningBid($itemId, $rfqResponseId, $aoqId) {
        return \App\Models\AoqEvaluation::where('procurement_id', $aoqId)
            ->where('rfq_response_id', $rfqResponseId)
            ->where('requirement', 'quote_' . $itemId)
            ->where('lowest_bid', true)
            ->exists();
    }
@endphp

<!-- Header -->
<div style="text-align:center; margin:0; padding:0;">
    <div style="display:inline-block; text-align:left; vertical-align:top;">
        <div style="display:table; margin:0 auto;">
            <div style="display:table-cell; vertical-align:middle; padding-right:12px;">
                <img src="{{ public_path('images/dict-logo-only.png') }}" alt="DICT Logo" style="width:80px; height:80px;">
            </div>
            <div style="display:table-cell; text-align:center; vertical-align:middle; font-family:Arial,sans-serif;">
                <div style="font-size:10px; color:#1055C9; font-weight:bold; letter-spacing:0.5px;">REPUBLIC OF THE PHILIPPINES</div>
                <div style="border-top:1px solid black; margin:2px 0;"></div>
                <div style="font-size:11px; color:#05339C; font-weight:bold; letter-spacing:0.3px; line-height:1.3;">
                    DEPARTMENT OF INFORMATION AND<br>COMMUNICATIONS TECHNOLOGY
                </div>
            </div>
        </div>
    </div>
    <p style="margin:8px 0 0 0; font-size:8px; text-align:center;">
        <strong>CORDILLERA ADMINISTRATIVE REGION</strong><br>
        DICT Compound, Polo Field, Saint Joseph Village, Baguio City 2600
    </p>
</div>

<!-- Title -->
<table class="no-border">
    <tr style="height:25px;"><td class="s3" colspan="26">ABSTRACT OF QUOTATION</td></tr>
    <tr style="height:8px;"><td class="s4" colspan="26"></td></tr>
</table>

<!-- Details -->
<table style="border:1px solid black; border-collapse:collapse; margin-left:10px; margin-bottom:15px; margin-top:15px; font-size:8pt;">
    <tr style="height:19px;">
        <td class="s5" colspan="7">Title/ Purpose:</td>
        <td class="s6 blue" colspan="19">{{ $aoq->title }}</td>
    </tr>
    <tr style="height:19px;">
        <td class="s5" colspan="7">ABC:</td>
        <td class="s7" colspan="4">₱{{ number_format($abc,2) }}</td>
        <td class="s8" colspan="4">End User:</td>
        <td class="s9" colspan="11">
            {{ $aoq->office_section == 'DICT CAR - Technical Operations Division' ? 'DICT CAR - Technical Operations Division' : 'DICT CAR - Admin and Finance Division' }}
        </td>
    </tr>
    <tr style="height:19px;">
        <td class="s5" colspan="7">Purchase Request No.:</td>
        <td class="s7" colspan="4">{{ $prNo }}</td>
        <td class="s8" colspan="4">Mode of Procurement:</td>
        <td class="s10" colspan="11">{{ $procurementType }}</td>
    </tr>
    <tr style="height:19px;">
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
    <tr style="height:19px;">
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
<table style="width:100%; border-collapse:collapse; font-size:9px;">
    <!-- Top Header -->
    <tr class="bold">
        <td colspan="5" style="border:1px solid black; text-align:center;">NAME OF BIDDER</td>
        @for($i=0;$i<3;$i++)
            <td colspan="2" style="border-top:2px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:2px solid black; text-align:center;">
                @if($i < $supplierCount)
                    {{ $suppliers[$i]->supplier->business_name ?? $suppliers[$i]->business_name ?? 'SUPPLIER '.($i+1) }}
                @else
                    SUPPLIER {{ $i+1 }}
                @endif
            </td>
        @endfor
        <td colspan="2" style="border:1px solid black; text-align:center;">Remarks</td>
    </tr>

    <!-- Eligibility Header -->
    <tr>
        <td colspan="5" style="border:1px solid black; font-weight:bold;">ELIGIBILITY REQUIREMENTS</td>
        @for($i=0;$i<3;$i++)<td colspan="2" style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:2px solid black;"></td>@endfor
        <td colspan="2" style="border:1px solid black;"></td>
    </tr>

    <!-- Eligibility Rows -->
    @foreach($eligibilityRequirements as $req)
        <tr>
            <td style="border:1px solid black;">{{ $req['letter'] }}</td>
            <td colspan="4" style="border:1px solid black;">{{ $req['label'] }}</td>
            @for($i=0;$i<3;$i++)
                @php
                    $supplier = $i < $supplierCount ? $suppliers[$i] : null;
                    $evalData = $supplier && isset($req['db_field']) ? getEvalStatus($supplier,$req['db_field'],$aoq->id) : ['status'=>'pending','remarks'=>''];
                    $cellText = $cellColor = '';
                    if($supplier){
                        $prefix = $evalData['status']==='pass' ? 'PASSED' : ($evalData['status']==='fail' ? 'FAILED' : '');
                        $cellText = $prefix . ($evalData['remarks'] ? ', '.$evalData['remarks'] : '');
                        if($evalData['status']==='fail') $cellColor = 'color:red;';
                    }
                @endphp
                <td colspan="2" style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:2px solid black; {{ $cellColor }}">
                    {{ $cellText }}
                </td>
            @endfor
            <td colspan="2" style="border:1px solid black; font-style:italic; text-align:center;">
                @if(isset($req['note'])){!! $req['note'] !!}@endif
            </td>
        </tr>
    @endforeach

    <!-- Remarks -->
    <tr class="bold center">
        <td colspan="5" style="border:1px solid black; text-align:right;">Remarks:</td>
        @for($i=0;$i<3;$i++)
            @php $supplier=$i<$supplierCount?$suppliers[$i]:null; $passed=$supplier?supplierPassed($supplier,$aoq->id):false; @endphp
            <td colspan="2" style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:2px solid black; {{ !$passed && $supplier ? 'color:red;' : '' }}">
                @if($supplier){{ $passed?'PASSED':'FAILED' }}@endif
            </td>
        @endfor
        <td colspan="2" style="border:1px solid black;"></td>
    </tr>

    <!-- Technical Specs Header -->
    <tr>
        <td colspan="5" style="border:1px solid black; font-weight:bold;">TECHNICAL SPECIFICATIONS</td>
        @for($i=0;$i<3;$i++)<td colspan="2" style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:2px solid black;"></td>@endfor
        <td colspan="2" style="border:1px solid black; font-weight:bold; text-align:center;">LOWEST BID</td>
    </tr>

    <!-- Technical Specs Table Header -->
    <tr class="center bold">
        <td style="border:1px solid black;">No.</td>
        <td style="border:1px solid black;">{{ $isLot?'Lot Description':'Item Description' }}</td>
        <td style="border:1px solid black;">Qty</td>
        <td style="border:1px solid black;">Unit</td>
        <td style="border:1px solid black;">ABC</td>
        @for($i=0;$i<3;$i++)
            <td style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:1px solid black;">Unit Value</td>
            <td style="border-top:1px solid black; border-bottom:1px solid black; border-left:1px solid black; border-right:2px solid black;">Total Value</td>
        @endfor
        <td style="border:1px solid black;">Unit Value</td>
        <td style="border:1px solid black;">Total Value</td>
    </tr>

    <!-- Item Rows -->
    @foreach($items as $index => $item)
        @php
            // Get quotes from the 3 displayed suppliers for this item
            $itemQuotes = [];
            foreach($suppliers as $s) {
                $itemQuotes[] = $s->quotes->firstWhere('procurement_item_id', $item->id);
            }

            // Get the actual winning bid from AoqEvaluation table (could be from any supplier, not just top 3)
            $winningBidData = getWinningBidForItem($item->id, $aoq->id, $rfqResponses);
        @endphp
        <tr>
            <td style="border:1px solid black; text-align:center;">{{ $item->sort }}</td>
            <td style="border:1px solid black;">{{ $item->item_description }}</td>
            <td style="border:1px solid black; text-align:center;">{{ $item->quantity }}</td>
            <td style="border:1px solid black; text-align:center;">{{ $item->unit }}</td>
            <td style="border:1px solid black; text-align:right;">{{ number_format($item->unit_cost,2) }}</td>
            
            @for($i=0;$i<3;$i++)
                @php
                    $quote = $itemQuotes[$i] ?? null;
                    $supplier = $i < $supplierCount ? $suppliers[$i] : null;
                    
                    // Check if THIS specific quote is the winning bid
                    $isWinner = false;
                    if ($quote && $supplier) {
                        $isWinner = isWinningBid($item->id, $supplier->id, $aoq->id);
                    }
                    
                    $bg = $isWinner ? 'background-color:#ffff99;' : '';
                    $txt = '';
                    
                    if ($quote && $supplier) {
                        $failed = !supplierPassed($supplier, $aoq->id);
                        $above = isAboveABC($quote, $item);
                        if ($failed || $above) {
                            $txt = 'color:red;';
                        }
                    }
                    
                    $cellStyle = $bg . $txt;
                @endphp
                <td style="border-top:1px solid black; border-bottom:1px solid black; border-left:2px solid black; border-right:1px solid black; text-align:right; {{ $cellStyle }}">
                    {{ $quote ? number_format($quote->unit_value, 2) : '0.00' }}
                </td>
                <td style="border-top:1px solid black; border-bottom:1px solid black; border-left:1px solid black; border-right:2px solid black; text-align:right; {{ $cellStyle }}">
                    {{ $quote ? number_format($quote->total_value, 2) : '0.00' }}
                </td>
            @endfor
            
            {{-- LOWEST BID column - shows the actual winner from evaluations --}}
            <td style="border:1px solid black; text-align:right; background-color:#ffffc8;">
                {{ $winningBidData ? number_format($winningBidData['quote']->unit_value, 2) : '0.00' }}
            </td>
            <td style="border:1px solid black; text-align:right; background-color:#ffffc8;">
                {{ $winningBidData ? number_format($winningBidData['quote']->total_value, 2) : '0.00' }}
            </td>
        </tr>
    @endforeach

    <!-- Total Row (empty cells) -->
    <tr class="bold">
        <td style="border-top:1px solid black; border-left:1px solid black; border-right:1px solid black;"></td>
        <td style="border-top:1px solid black; border-left:1px solid black; border-right:1px solid black;"></td>
        <td style="border-top:1px solid black; border-left:1px solid black; border-right:1px solid black;"></td>
        <td style="border-top:1px solid black; border-left:1px solid black; border-right:1px solid black;"></td>
        <td style="border-top:1px solid black; border-left:1px solid black; border-right:1px solid black;"></td>
        @for($i=0;$i<3;$i++)
            <td style="border-top:1px solid black; border-left:2px solid black; border-right:1px solid black;"></td>
            <td style="border-top:1px solid black; border-left:1px solid black; border-right:2px solid black;"></td>
        @endfor
        <td colspan="2" style="border-top:1px solid black; border-left:1px solid black; border-right:1px solid black; text-align:left; font-weight:bold;">TOTAL</td>
    </tr>

    <!-- Peso sign row -->
    <tr class="bold">
        <td colspan="5" style="border-left:1px solid black; border-right:1px solid black; border-bottom:1px solid black;"></td>
        @for($i=0;$i<3;$i++)
            @php
                $supplier = $i < $supplierCount ? $suppliers[$i] : null;
                $total = $supplier ? $supplier->quotes->sum('total_value') : 0;
                $failed = $supplier ? !supplierPassed($supplier, $aoq->id) : false;
                $txt = $failed ? 'color:red;' : '';
            @endphp
            <td style="border-left:2px solid black; border-right:1px solid black; border-bottom:2px solid black;"></td>
            <td style="border-left:1px solid black; border-right:2px solid black; border-bottom:2px solid black; text-align:right; {{ $txt }}">
                P {{ number_format($total, 2) }}
            </td>
        @endfor
        @php
            // For per-item: sum all winning bids
            // For per-lot: use the lowest qualified total
            if ($isLot) {
                $lowestTotal = PHP_FLOAT_MAX;
                foreach($suppliers as $s) {
                    if(supplierPassed($s, $aoq->id)) {
                        $st = $s->quotes->sum('total_value');
                        if($st > 0 && $st < $lowestTotal) {
                            $lowestTotal = $st;
                        }
                    }
                }
                if($lowestTotal === PHP_FLOAT_MAX) $lowestTotal = 0;
            } else {
                // Per-item: sum all winning quotes
                $lowestTotal = 0;
                foreach($items as $item) {
                    $winningBidData = getWinningBidForItem($item->id, $aoq->id, $rfqResponses);
                    if ($winningBidData) {
                        $lowestTotal += $winningBidData['quote']->total_value;
                    }
                }
            }
        @endphp
        <td colspan="2" style="border:1px solid black; text-align:right; background-color:#ffffc8;">
            P {{ number_format($lowestTotal, 2) }}
        </td>
    </tr>
</table>

@php
    $aoqApprovals = $aoq->approvals()
        ->where('module','abstract_of_quotation')
        ->orderBy('sequence')
        ->with('employee.certificate')
        ->get();

    // Count who actually APPROVED (status = Approved + has signature)
    $approved = $aoqApprovals->where('status', 'Approved')->whereNotNull('signature');

    $chairperson = $approved->where('sequence', 5)->first();  // BAC Chairperson
    $viceChair   = $approved->where('sequence', 4)->first();   // Vice-Chair
    $members     = $approved->whereIn('sequence', [1,2,3]);   // BAC Members + Provisional

    $hasChairOrVice = $chairperson || $viceChair;
    $hasTwoMembers  = $members->count() >= 2;

    // This is the magic boolean
    $isFullyApproved = $approved->count() >= 3 && $hasChairOrVice && $hasTwoMembers;
@endphp

<!-- Prepared / Legend -->
<br><br>
<table class="no-border signatories" style="width:100%; text-align:center;">
    <tr>
        <td style="width:30%; text-align:left; border:none; padding:5px; vertical-align:top;">
            <b>Prepared by:</b><br><br>
            <div style="text-align:center;">
                @if($aoq->preparer && $aoq->preparer->certificate && $aoq->preparer->certificate->signature_image_path)
                    @php
                        try {
                            $signature = \Illuminate\Support\Facades\Crypt::decryptString($aoq->preparer->certificate->signature_image_path);
                        } catch (\Exception $e) {
                            $signature = null;
                        }
                    @endphp
                    @if($signature)
                        <div class="signature-container">
                            <img src="data:image/png;base64,{{ $signature }}" class="signature-img" alt="Signature">
                        </div>
                    @else
                        <div style="height:25px;"></div>
                    @endif
                @else
                    <div style="height:25px;"></div>
                @endif
                
                <!-- Name -->
                <span class="underline">
                    {{ strtoupper($aoq->preparer->full_name ?? 'NOT SET') }}
                </span><br>

                <!-- Designation only if exists -->
                @if($aoq->preparer?->designation || $aoq->preparer?->position?->name)
                    <i>{{ $aoq->preparer->designation ?? $aoq->preparer->position?->name }}</i>
                @endif
            </div>
        </td>
        <td style="width:60%; text-align:left; border:none; padding:5px 50px 0 0; vertical-align:top;">
            <b>Legend:</b><br>
            <div class="legend">
                <span class="red">Red Mark</span> – Disqualified / Above ABC / NA / Did not comply in Statement of Compliance / Did not meet minimum specifications<br>
                <span class="highlight">Highlighted</span> – Lowest Bid / Lowest Responsive Bid
            </div>
        </td>
    </tr>
</table>

<br><br>

<!-- Reviewed by-->
@php
use App\Models\DefaultApprover;
use Illuminate\Support\Facades\Crypt;

/*
 |-------------------------------------------------------
 | Fetch approver from DefaultApprover by sequence
 |-------------------------------------------------------
*/
function getAoqDefault($seq) {
    return DefaultApprover::where('module', 'abstract_of_quotation')
        ->where('sequence', $seq)
        ->with(['employee.certificate'])
        ->first();
}

$member1 = getAoqDefault(1);
$member2 = getAoqDefault(2);
$member3 = getAoqDefault(3);
$vice    = getAoqDefault(4);
$chair   = getAoqDefault(5);

/*
 |-------------------------------------------------------
 | Build signatory block (signature + name + designation)
 |-------------------------------------------------------
*/
function aoq_signatory_block($defaultApprover, $fallbackLabel) {

    if (!$defaultApprover) {
        return '
            <div style="height:25px;"></div>
            <span class="underline">__________________</span><br>
            <i>'.$fallbackLabel.'</i>
        ';
    }

    $employee = $defaultApprover->employee;
    $name = strtoupper($employee->full_name ?? 'NOT SET');
    $designation = $defaultApprover->designation ?? $fallbackLabel;

    // Handle signature decryption if available
    $signature = null;
    if ($employee?->certificate?->signature_image_path) {
        try {
            $signature = Crypt::decryptString($employee->certificate->signature_image_path);
        } catch (\Exception $e) {
            $signature = null;
        }
    }

    return '
        '.($signature ?
            '<div class="signature-container">
                <img class="signature-img" src="data:image/png;base64,'.$signature.'">
            </div>'
            :
            '<div style="height:25px;"></div>'
        ).'
        <span class="underline">'.$name.'</span><br>
        <i>'.$designation.'</i>
    ';
}
@endphp

<table class="no-border" style="width:100%; margin-top:30px; text-align:center;">
    <tr>
        <td colspan="6" style="text-align:left; border:none; padding:5px;">
            <b>Reviewed by:</b>
        </td>
    </tr>

    <!-- Row 1: BAC Members -->
    <tr>
        <td colspan="2" style="border:none;">
            {!! aoq_signatory_block($member1, 'BAC Member') !!}
        </td>

        <td colspan="2" style="border:none;">
            {!! aoq_signatory_block($member2, 'BAC Member') !!}
        </td>

        <td colspan="2" style="border:none;">
            {!! aoq_signatory_block($member3, 'Provisional Member') !!}
        </td>
    </tr>

    <!-- Row 2: Vice-Chairperson and Chairperson -->
    <tr>
        <td style="border:none;"></td>

        <td colspan="2" style="border:none;">
            {!! aoq_signatory_block($vice, 'BAC Vice-Chairperson') !!}
        </td>

        <td colspan="2" style="border:none;">
            {!! aoq_signatory_block($chair, 'BAC Chairperson') !!}
        </td>

        <td style="border:none;"></td>
    </tr>
</table>

</body>
</html>