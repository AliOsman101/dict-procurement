<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Minutes of Opening of Bid Price Quotation</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .no-border td, .no-border th {
            border: none !important;
        }
        .s3 {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            background-color: #ffffc8;
        }
        .section-title {
            text-align: center;
            font-weight: bold;
            margin: 10px 0;
        }
        .text-justify {
            text-align: justify;
            line-height: 1.6;
        }
        .text-indent {
            text-indent: 40px;
        }
        .center {
            text-align: center;
        }
        .underline {
            display: inline-block;
            border-bottom: 1px solid black;
            min-width: 220px;
        }
        .signatories td {
            border: none;
            text-align: left;
            padding: 5px;
            vertical-align: top;
        }
        .signature-container {
            width: 100%;
            height: 30px;
            text-align: center;
            overflow: hidden;
            margin: 0 0 5px 0;
            line-height: 30px;
        }
        .signature-img {
            max-width: 100px;
            max-height: 30px;
            width: auto;
            height: auto;
            display: inline-block;
            vertical-align: middle;
            mix-blend-mode: multiply;
            object-fit: contain;
        }
        .signatory-name {
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 220px;
            text-align: center;
        }
        .supplier-bullets {
            margin-left: 20px;
            margin-top: 5px;
            line-height: 1.5;
        }
        .winning-supplier {
            margin-top: 20px;
        }
        .prepared-by {
            margin-top: 40px;
        }
    </style>
</head>
<body>

<!-- Header -->
<div style="text-align:center; margin:0; padding:0;">
    <div style="display:inline-block; text-align:left; vertical-align:top;">
        <div style="display:table; margin:0 auto;">
            <div style="display:table-cell; vertical-align:middle; padding-right:12px;">
                <img src="{{ public_path('images/dict-logo-only.png') }}" alt="DICT Logo" style="width:80px; height:80px;">
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
    <tr><td class="s3">MINUTES OF OPENING OF BID PRICE QUOTATION</td></tr>
</table>

<!-- Time & Date -->
<div style="margin-top: 15px;">
    <strong>TIME & DATE:</strong>
    {{ $aoq->bid_opening_datetime ? $aoq->bid_opening_datetime->format('F j, Y \a\t g:i A') : 'Not set' }}
</div>

<!-- Present -->
@php
    // Get BAC members from DefaultApprover for AOQ module
    $bacDefaultApprovers = \App\Models\DefaultApprover::where('module', 'abstract_of_quotation')
        ->with('employee')
        ->get();
    
    // Hardcoded designation order - these are static
    $designationOrder = [
        'Chairperson',
        'Vice - Chairperson',
        'Regular Member (Principal)',
        'Regular Member (Principal)',
        'Provisional Member/End-User',
    ];
    
    // Find employee names by matching their designation in DefaultApprover
    $bacMembers = [];
    
    // Find Chairperson
    $chairperson = $bacDefaultApprovers->first(function($approver) {
        return stripos($approver->designation, 'Chairperson') !== false && 
               stripos($approver->designation, 'Vice') === false;
    });
    $bacMembers[] = [
        'name' => strtoupper($chairperson?->employee?->full_name ?? 'NOT SET'),
        'designation' => 'Chairperson',
    ];
    
    // Find Vice-Chairperson
    $viceChair = $bacDefaultApprovers->first(function($approver) {
        return stripos($approver->designation, 'Vice') !== false && 
               stripos($approver->designation, 'Chair') !== false;
    });
    $bacMembers[] = [
        'name' => strtoupper($viceChair?->employee?->full_name ?? 'NOT SET'),
        'designation' => 'Vice - Chairperson',
    ];
    
    // Find Regular Members (need 2)
    $members = $bacDefaultApprovers->filter(function($approver) {
        return (stripos($approver->designation, 'Member') !== false || 
                stripos($approver->designation, 'Regular') !== false) &&
               stripos($approver->designation, 'Provisional') === false &&
               stripos($approver->designation, 'Vice') === false &&
               stripos($approver->designation, 'Chair') === false;
    })->take(2)->values();
    
    foreach ($members as $member) {
        $bacMembers[] = [
            'name' => strtoupper($member->employee?->full_name ?? 'NOT SET'),
            'designation' => 'Regular Member (Principal)',
        ];
    }
    
    // If we don't have 2 members yet, add placeholders
    while (count($bacMembers) < 4) {
        $bacMembers[] = [
            'name' => 'NOT SET',
            'designation' => 'Regular Member (Principal)',
        ];
    }
    
    // Find Provisional Member
    $provisional = $bacDefaultApprovers->first(function($approver) {
        return stripos($approver->designation, 'Provisional') !== false;
    });
    $bacMembers[] = [
        'name' => strtoupper($provisional?->employee?->full_name ?? 'NOT SET'),
        'designation' => 'Provisional Member/End-User',
    ];
@endphp

<div style="margin-top: 15px;">
    <strong>PRESENT:</strong>
</div>

<table style="width: 100%; margin-top: 5px; border-collapse: collapse;">
    @foreach($bacMembers as $member)
        <tr>
            <td style="width: 35%; padding: 4px 0; border: none; vertical-align: bottom;">
                {{ $member['name'] }}
            </td>
            <td style="width: 35%; padding: 4px 0; border: none; vertical-align: bottom; text-align: left;">
                {{ $member['designation'] }}
            </td>
            <td style="width: 30%; padding: 4px 0; border: none; vertical-align: bottom;">
                <div style="border-bottom: 1px solid black; width: 100%; height: 1px;"></div>
            </td>
        </tr>
    @endforeach
</table>

<!-- Items Deliberated -->
<div style="margin-top: 20px;">
    <strong>ITEMS DELIBERATED AND RECOMMENDATIONS:</strong>
</div>

@php
    // Get mode of procurement from PR and format it
    $modeOfProcurement = $pr->procurement_type ?? '52.1 sec.b Shopping';
    
    // Format procurement type if it contains underscores
    if (strpos($modeOfProcurement, '_') !== false) {
        $modeOfProcurement = ucwords(str_replace('_', ' ', $modeOfProcurement));
    }
    
    // Number to words helper
    function numberToWords($num) {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 
                 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 
                 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        
        if ($num < 20) return $ones[$num];
        if ($num < 100) return $tens[intval($num/10)] . ($num%10 ? ' ' . $ones[$num%10] : '');
        return (string)$num;
    }
    
    $supplierCount = $rfqResponses->count();
    $supplierText = numberToWords($supplierCount) . ' (' . $supplierCount . ')';
@endphp

<table style="margin-top: 10px; font-size: 10px; border-collapse: collapse; width: 100%;">
    <tr style="border: none;">
        <td style="padding: 5px; border: none; vertical-align: top; width: 40%;">• Procurement Project Title/ Purpose:</td>
        <td style="padding: 5px; border: none; vertical-align: top;">{{ $aoq->title }}</td>
    </tr>
    <tr style="border: none;">
        <td style="padding: 5px; border: none; vertical-align: top;">• Approved Budget Contract (ABC):</td>
        <td style="padding: 5px; border: none; vertical-align: top;">₱{{ number_format($pr->grand_total, 2) }}</td>
    </tr>
    <tr style="border: none;">
        <td style="padding: 5px; border: none; vertical-align: top;">• Purchase Request No.:</td>
        <td style="padding: 5px; border: none; vertical-align: top;">{{ $pr->procurement_id }}</td>
    </tr>
    <tr style="border: none;">
        <td style="padding: 5px; border: none; vertical-align: top;">• Request for Quotation No.:</td>
        <td style="padding: 5px; border: none; vertical-align: top;">{{ $rfq->procurement_id }}</td>
    </tr>
    <tr style="border: none;">
        <td style="padding: 5px; border: none; vertical-align: top;">• Mode of Procurement:</td>
        <td style="padding: 5px; border: none; vertical-align: top;">{{ $modeOfProcurement }}</td>
    </tr>
    <tr style="border: none;">
        <td style="padding: 5px; border: none; vertical-align: top;">• Delivery Period (CD):</td>
        <td style="padding: 5px; border: none; vertical-align: top;">
            @if($rfq->delivery_mode === 'days')
                within {{ $rfq->delivery_value }} calendar days upon receipt of purchase order
            @elseif($rfq->delivery_mode === 'date')
                {{ \Carbon\Carbon::parse($rfq->delivery_value)->format('F j, Y') }}
            @else
                Not set
            @endif
        </td>
    </tr>
    <tr style="border: none;">
        <td style="padding: 5px; border: none; vertical-align: top;">• Place of Delivery:</td>
        <td style="padding: 5px; border: none; vertical-align: top;">{{ $rfq->place_of_delivery ?? 'DICT CAR, Baguio City' }}</td>
    </tr>
    <tr style="border: none;">
        <td colspan="2" style="padding: 5px; border: none;">
            • Award will be on {{ $pr->basis === 'lot' ? 'per lot' : 'per item' }} basis.
        </td>
    </tr>
    <tr style="border: none;">
        <td colspan="2" style="padding: 5px; border: none;">
            • Request for Quotation was posted in a conspicuous place within the premise of the procuring entity.
        </td>
    </tr>
    <tr style="border: none;">
        <td colspan="2" style="padding: 5px; border: none;">
            • Request for Quotation was sent to suppliers with well known qualification.
        </td>
    </tr>
    <tr style="border: none;">
        <td colspan="2" style="padding: 5px; border: none;">
            <strong>•&nbsp;&nbsp;&nbsp;&nbsp;{{ $supplierText }} supplier{{ $supplierCount !== 1 ? 's' : '' }} submitted their quotation.</strong>
        </td>
    </tr>
</table>

<!-- Suppliers Evaluation -->
<div style="margin-top: 15px; margin-left: 30px;">
    @foreach($rfqResponses as $response)
        @php
            $supplierName = strtoupper($response->supplier?->business_name ?? $response->business_name ?? 'Unknown Supplier');

            // Eligibility & Tech Pass
            $docEvals = \App\Models\AoqEvaluation::where('procurement_id', $aoq->id)
                ->where('rfq_response_id', $response->id)
                ->where('requirement', 'not like', 'quote_%')
                ->get();

            $passedEligibility = !$docEvals->contains('status', 'fail');
            $passedTech = $response->quotes->where('statement_of_compliance', true)->count() === $response->quotes->count();

            // Winning items
            $winningItems = \App\Models\AoqEvaluation::where('procurement_id', $aoq->id)
                ->where('rfq_response_id', $response->id)
                ->where('lowest_bid', true)
                ->get()
                ->map(fn($eval) => $eval->requirement_id)
                ->map(fn($id) => \App\Models\ProcurementItem::find($id)?->sort)
                ->filter()
                ->sort()
                ->values();

            // Group consecutive items
            $groupedItems = [];
            if ($winningItems->isNotEmpty()) {
                $start = $winningItems[0];
                $end = $start;
                
                for ($i = 1; $i < $winningItems->count(); $i++) {
                    if ($winningItems[$i] == $end + 1) {
                        $end = $winningItems[$i];
                    } else {
                        $groupedItems[] = $start == $end ? "$start" : "$start-$end";
                        $start = $winningItems[$i];
                        $end = $start;
                    }
                }
                $groupedItems[] = $start == $end ? "$start" : "$start-$end";
            }
            
            $winningItemsStr = implode(',', $groupedItems);

            // Tie-breaking note
            $tieNote = '';
            if ($winningItemsStr) {
                $tieRecords = \DB::table('aoq_tie_breaking_records')
                    ->where('procurement_id', $aoq->id)
                    ->where('winner_rfq_response_id', $response->id)
                    ->get();

                foreach ($tieRecords as $record) {
                    $itemSort = \App\Models\ProcurementItem::find($record->procurement_item_id)?->sort ?? 'Unknown';
                    $method = $record->method === 'coin_toss' ? 'toss coin' : 'random draw';
                    if ($tieNote) $tieNote .= "\n";
                    $tieNote .= "• Item {$itemSort} awarded thru {$method}";
                }
            }

            // Failed items above ABC
            $failedItems = \App\Models\AoqEvaluation::where('procurement_id', $aoq->id)
                ->where('rfq_response_id', $response->id)
                ->where('status', 'fail')
                ->where('requirement', 'like', 'quote_%')
                ->get()
                ->map(fn($eval) => $eval->requirement_id)
                ->map(fn($id) => \App\Models\ProcurementItem::find($id)?->sort)
                ->filter()
                ->sort()
                ->implode(', ');
        @endphp

        <div style="margin-bottom: 15px; font-weight: normal;">
            {{ $supplierName }}
            <div class="supplier-bullets">
                <div>• {{ $passedEligibility && $passedTech ? 'Passed' : 'Failed' }} eligibility requirements and technical specifications.</div>
                @if($winningItemsStr)
                    <div>• Lowest Bid as read for items {{ $winningItemsStr }}</div>
                @endif
                @if($tieNote)
                    <div>{!! nl2br($tieNote) !!}</div>
                @endif
                @if(!$passedEligibility && !$passedTech)
                    <div>• Passed eligibility requirements, but failed technical specifications for not checking statement of compliance.</div>
                @elseif(!$passedTech)
                    <div>• Failed technical specifications for not checking statement of compliance.</div>
                @endif
                @if($failedItems)
                    <div>• Items {{ $failedItems }} above ABC, failed items for reposting</div>
                @endif
            </div>
        </div>
    @endforeach
</div>

<!-- Lowest Calculated and Responsive Bid -->
<div class="winning-supplier">
    <strong>• Lowest Calculated and Responsive Bid:</strong>
    <div style="margin-left: 60px; margin-top: 8px; font-weight: normal;">
        @foreach($winningSuppliers as $sup)
            <strong>{{ strtoupper($sup['name']) }} (Items {{ $sup['items'] }}) amounting to ₱{{ number_format($sup['total'], 2) }}</strong>
            <br>
        @endforeach
    </div>
</div>

<!-- Signatures -->
@php
    // Get BAC Secretariat from Employee with designation containing "Secretariat"
    $secretariat = \App\Models\Employee::where(function($q) {
            $q->where('designation', 'like', '%BAC Secretariat%')
              ->orWhere('designation', 'like', '%Secretariat%');
        })
        ->with('certificate')
        ->first();
    
    // Get MO default approver (the approver for minutes_of_opening module)
    $moApprover = \App\Models\DefaultApprover::where('module', 'minutes_of_opening')
        ->with('employee.certificate')
        ->first();
@endphp

<table class="signatories prepared-by">
    <tr>
        <td style="width: 50%;">
            <div style="font-weight: bold;">Prepared by:</div>
            <div style="margin-top: 40px; text-align: center;">
                <div class="signature-container">
                    @if($secretariat && $secretariat->certificate?->signature_image_path)
                        @php
                            try { 
                                $sig = \Illuminate\Support\Facades\Crypt::decryptString($secretariat->certificate->signature_image_path); 
                            } catch (\Exception $e) { 
                                $sig = null; 
                            }
                        @endphp
                        @if($sig)
                            <img src="data:image/png;base64,{{ $sig }}" class="signature-img">
                        @endif
                    @endif
                </div>
                <div class="signatory-name">
                    {{ $secretariat ? strtoupper($secretariat->full_name) : 'NOT SET' }}
                </div>
                <div style="margin-top: 3px; font-style: italic;">
                    {{ $secretariat ? $secretariat->designation : 'BAC Secretariat' }}
                </div>
            </div>
        </td>
        <td style="width: 50%;">
            <div style="font-weight: bold;">Approved by:</div>
            <div style="margin-top: 40px; text-align: center;">
                <div class="signature-container">
                    @if($moApprover && $moApprover->employee?->certificate?->signature_image_path)
                        @php
                            try { 
                                $sig = \Illuminate\Support\Facades\Crypt::decryptString($moApprover->employee->certificate->signature_image_path); 
                            } catch (\Exception $e) { 
                                $sig = null; 
                            }
                        @endphp
                        @if($sig)
                            <img src="data:image/png;base64,{{ $sig }}" class="signature-img">
                        @endif
                    @endif
                </div>
                <div class="signatory-name">
                    {{ $moApprover && $moApprover->employee ? strtoupper($moApprover->employee->full_name) : 'NOT SET' }}
                </div>
                <div style="margin-top: 3px; font-style: italic;">
                    {{ $moApprover ? $moApprover->designation : 'MO Approver' }}
                </div>
            </div>
        </td>
    </tr>
</table>

</body>
</html>