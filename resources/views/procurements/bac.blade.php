<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>BAC Resolution Recommending Award</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid black; padding: 4px; vertical-align: middle; }
        .no-border td, .no-border th { border: none !important; }
        .s3 { text-align: center; font-weight: bold; font-size: 11pt; background-color: #ffffc8; }
        .section-title { text-align: center; font-weight: bold; margin: 10px 0; }
        .resolution-text { text-align: justify; line-height: 1.5; margin: 10px 40px; text-indent: 40px; }
        .signatories td { border: none; text-align: center; padding: 20px 5px 5px 5px; }
        .approved-by { margin-top: 40px; font-weight: bold; }
        .center { text-align: center; }
        .underline { display: inline-block; border-bottom: 1px solid black; min-width: 200px; }
        
        /* Ensure single-line names without cutting */
        .signatory-name {
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
            min-width: 0;
            max-width: 100%;
        }
        
        /* Signature container - ADAPTIVE sizing that NEVER disrupts layout */
        .signature-container {
            width: 100%;
            height: 30px;
            text-align: center;
            overflow: hidden;
            margin: 0 0 5px 0;
            padding: 0;
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
        <tr><td class="s3">BAC RESOLUTION RECOMMENDING AWARD</td></tr>
    </table>

    <!-- PROCUREMENT OF -->
    @php
        $pr = $procurement->parent ? $procurement->parent->children()->where('module', 'purchase_request')->first() : null;
        $prNo = $pr ? $pr->procurement_id : 'Not set';
        $abc = $pr ? $pr->grand_total : 0;
        $procurementType = $procurement->procurement_type === 'small_value_procurement' ? 'Small Value Procurement' : 'Public Bidding';
        $basis = $pr ? ($pr->basis === 'lot' ? 'lot' : 'item') : 'item';
        $procurementTitle = $procurement->title ?? 'Not set';
    @endphp
    <p class="section-title">PROCUREMENT OF {{ strtoupper($procurementTitle) }}</p>

    <!-- BAC Resolution Number -->
    <table style="width:100%; border:none; margin-top:10px;">
        <tr>
            <td style="text-align:right; border:none; width:50%;">BAC Resolution No.:</td>
            <td style="text-align:left; border:none; width:50%;">
                <span style="display:inline-block; min-width:200px;">{{ $procurement->procurement_id ?? 'Not set' }}</span>
            </td>
        </tr>
    </table>

    <!-- Resolution Body -->
    @php
        // Get the AOQ procurement (parent of BAC Resolution)
        $aoq = $procurement->parent ? $procurement->parent->children()->where('module', 'abstract_of_quotation')->first() : null;
        
        // Check if there's a tie-breaking record
        $tieBreakingRecord = $aoq ? \DB::table('aoq_tie_breaking_records')
            ->where('procurement_id', $aoq->id)
            ->first() : null;
        
        if ($tieBreakingRecord) {
            // If tie-breaking occurred, get winner from record
            $winnerRfqResponse = \App\Models\RfqResponse::with('supplier', 'quotes')
                ->find($tieBreakingRecord->winner_rfq_response_id);
            
            $winningSuppliers = $winnerRfqResponse->supplier?->business_name ?? $winnerRfqResponse->business_name ?? $tieBreakingRecord->winner_supplier_name;
            $totalContractAmount = $winnerRfqResponse->quotes->sum('total_value');
        } else {
            // Fetch winning suppliers based on AoqEvaluations with lowest_bid = true from AOQ
            $winningEvaluations = $aoq ? \App\Models\AoqEvaluation::where('procurement_id', $aoq->id)
                ->where('lowest_bid', true)
                ->with(['rfqResponse.supplier', 'rfqResponse.quotes'])
                ->get() : collect();
            
            // Get unique suppliers
            $winningSuppliers = $winningEvaluations->map(function ($evaluation) {
                return $evaluation->rfqResponse->supplier?->business_name ?? $evaluation->rfqResponse->business_name ?? 'Unknown Supplier';
            })->unique()->implode(', ');
            
            // Calculate total contract amount from winning quotes
            $totalContractAmount = 0;
            foreach ($winningEvaluations as $evaluation) {
                if ($evaluation->rfqResponse && $evaluation->rfqResponse->quotes) {
                    $totalContractAmount += $evaluation->rfqResponse->quotes->sum('total_value');
                }
            }
        }
    @endphp
    <div style="margin:0; padding:0; text-align:justify; text-indent:30px; margin-top:10px;">
        The Bids and Awards Committee hereby recommends to award to the 
        <span style="font-weight:bold;">Lowest Calculated and Responsive Bid</span> on a 
        <span style="font-weight:bold;">per</span> 
        <span style="font-weight:bold;">{{ $basis }}</span> 
        <span style="font-weight:bold;">basis</span>, for the 
        <span style="font-weight:bold;">{{ strtoupper($procurementTitle) }}</span> with PR No. 
        <span style="font-weight:bold;">{{ $prNo }}</span> with approved budget contract of 
        <span style="font-weight:bold;">{{ strtoupper(\App\Helpers\NumberToWords::transformNumber('en', $abc)) }} PESOS ONLY (Php {{ number_format($abc, 2) }})</span> under 
        <span style="font-weight:bold;">{{ $procurementType }}</span> as a mode of procurement.  
        It is hereby recommended that the award be in favor of 
        <span style="font-weight:bold;">{{ strtoupper($winningSuppliers) }}</span> with a contract amount of 
        <span style="font-weight:bold;">{{ strtoupper(\App\Helpers\NumberToWords::transformNumber('en', $totalContractAmount)) }} PESOS ONLY (Php {{ number_format($totalContractAmount, 2) }})</span>.
    </div>

    <!-- Closing -->
    @php
        $resolutionDate = $procurement->approvals()->where('module', 'bac_resolution_recommending_award')
            ->where('status', 'Approved')
            ->first()?->date_approved ?? now();
        $day = \Carbon\Carbon::parse($resolutionDate)->format('j');
        $month = \Carbon\Carbon::parse($resolutionDate)->format('F');
        $year = \Carbon\Carbon::parse($resolutionDate)->format('Y');
        
        // Generate ordinal suffix manually
        $suffix = 'th';
        if ($day % 10 == 1 && $day != 11) {
            $suffix = 'st';
        } elseif ($day % 10 == 2 && $day != 12) {
            $suffix = 'nd';
        } elseif ($day % 10 == 3 && $day != 13) {
            $suffix = 'rd';
        }
    @endphp
    <div style="margin:0; text-align:justify; margin-top: 15px;">
        Resolved, at DICT Compound, Polo Field, St. Joseph Village, Baguio City this 
        {{ $day }}<sup>{{ $suffix }}</sup> day of {{ $month }} {{ $year }}.
    </div>

    <!-- Signatories -->
    @php
        // Get BAC Resolution Approvals with Signatures
        $bacApprovals = $procurement->approvals()
            ->where('module', 'bac_resolution_recommending_award')
            ->orderBy('sequence')
            ->with('employee.certificate')
            ->get();

        // Get unique Regular Members (max 2), avoiding duplicates by employee_id
        $regularMembers = $bacApprovals->whereIn('designation', ['Regular Member', 'Regular Member (Principal)'])
            ->unique('employee_id')
            ->values()
            ->take(2);

        // Assign other roles based on designation
        $provisionalMember = $bacApprovals->whereIn('designation', ['Provisional Member', 'Provisional Member / End-User'])->first();
        $viceChairperson = $bacApprovals->where('designation', 'Vice - Chairperson')->first();
        $chairperson = $bacApprovals->where('designation', 'Chairperson')->first();
    @endphp
    <table class="no-border" style="width:100%; text-align:center; margin-top: 30px; border:none;">
        <tr>
            @foreach($regularMembers as $member)
                <td colspan="2" style="width:33.33%; text-align:center; border:none; padding: 10px 5px 5px 5px;">
                    @if($member->signature)
                        <div class="signature-container">
                            <img src="data:image/png;base64,{{ $member->signature }}" 
                                 class="signature-img"
                                 alt="Signature">
                        </div>
                    @else
                        <div style="height:30px; margin-bottom:5px;"></div>
                    @endif
                    <div class="signatory-name" style="border-bottom:1px solid black; width:80%; margin:0 auto;">
                        <span style="font-weight:bold; text-transform:uppercase;">{{ $member->employee->full_name ?? '' }}</span>
                    </div>
                    <i>Regular Member (Principal)</i>
                </td>
            @endforeach
            <td colspan="2" style="width:33.33%; text-align:center; border:none; padding: 10px 5px 5px 5px;">
                @if($provisionalMember && $provisionalMember->signature)
                    <div class="signature-container">
                        <img src="data:image/png;base64,{{ $provisionalMember->signature }}" 
                             class="signature-img"
                             alt="Signature">
                    </div>
                @else
                    <div style="height:30px; margin-bottom:5px;"></div>
                @endif
                <div class="signatory-name" style="border-bottom:1px solid black; width:80%; margin:0 auto;">
                    <span style="font-weight:bold; text-transform:uppercase;">{{ $provisionalMember?->employee?->full_name ?? '' }}</span>
                </div>
                <i>Provisional Member / End-User</i>
            </td>
        </tr>
        <tr>
            <td style="width:16.67%; border:none;"></td>
            <td colspan="2" style="width:33.33%; text-align:center; border:none; padding: 30px 5px 5px 5px;">
                @if($viceChairperson && $viceChairperson->signature)
                    <div class="signature-container">
                        <img src="data:image/png;base64,{{ $viceChairperson->signature }}" 
                             class="signature-img"
                             alt="Signature">
                    </div>
                @else
                    <div style="height:30px; margin-bottom:5px;"></div>
                @endif
                <div class="signatory-name" style="border-bottom:1px solid black; width:60%; margin:0 auto;">
                    <span style="font-weight:bold; text-transform:uppercase;">{{ $viceChairperson?->employee?->full_name ?? '' }}</span>
                </div>
                <i>Vice - Chairperson</i>
            </td>
            <td colspan="2" style="width:33.33%; text-align:center; border:none; padding: 30px 5px 5px 5px;">
                @if($chairperson && $chairperson->signature)
                    <div class="signature-container">
                        <img src="data:image/png;base64,{{ $chairperson->signature }}" 
                             class="signature-img"
                             alt="Signature">
                    </div>
                @else
                    <div style="height:30px; margin-bottom:5px;"></div>
                @endif
                <div class="signatory-name" style="border-bottom:1px solid black; width:60%; margin:0 auto;">
                    <span style="font-weight:bold; text-transform:uppercase;">{{ $chairperson?->employee?->full_name ?? '' }}</span>
                </div>
                <i>Chairperson</i>
            </td>
            <td style="width:16.67%; border:none;"></td>
        </tr>
    </table>

    <!-- Approved By -->
    <div class="approved-by">
        Approved By:
    </div>
    <div style="text-align:left; margin-top:40px; margin-left:30px;">
        <b>ENGR. REYNALDO T. SY</b><br>
        Regional Director<br>
        Head of Procuring Entity (HOPE)
    </div>

</body>
</html>