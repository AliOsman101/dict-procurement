<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Request</title>
    <style>
        body { font-family: 'Play', 'DejaVu Sans', sans-serif; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { border: 1px solid black; padding: 4px; vertical-align: middle; }
        th { background-color: #f2f2f2; text-align: center; }
        .center { text-align: center; }
        .right { text-align: right; }
        .highlight-yellow { background-color: #ffffc8;  font-weight: bold; text-align: center; font-family: "Arial Black", sans-serif; font-size: 11px; }
        .purple { color: purple; font-weight: bold; text-align: center; font-size: 10px; }
        .blue { color: blue; font-weight: bold; }
        .no-border td { border: none !important; }
        .s3 { text-align: center; font-weight: bold; font-size: 10pt; background-color: #ffffc8;  }
        .s4 {border-bottom: 1px solid #000;}

        /* Entity + Fund Cluster styling */
        .inline-label { font-weight: bold; white-space: nowrap; }
        .underline {
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 100px;
            text-indent: 3px;
        }
        .entity-cell, .fund-cell {
            vertical-align: top;
            padding: 2px 0;
        }
        .entity-text, .fund-text {
            display: inline-block;
            line-height: 1.3;
        }
        /* Hanging indent for wrapped text */
        .entity-text span, .fund-text span {
            display: block;
            text-indent: 15px;
        }
        i { font-style: italic; }
        .italic-label { font-style: italic; }
        
        /* Signature container - ADAPTIVE sizing that NEVER disrupts layout */
        .signature-container {
            width: 100%;
            height: 30px;
            text-align: center;
            overflow: hidden;
            margin: 0;
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

                    <div style="font-size:11px; color: #05339C; text-align:center;  font-weight:bold; letter-spacing:0.3px; line-height:1.3;">
                        DEPARTMENT OF INFORMATION AND<br>COMMUNICATIONS TECHNOLOGY
                    </div>
                </div>
            </div>
        </div>

    <!-- Address -->
    <p style="margin:8px 0 0 0; font-size:8px; text-align:center;">
        <strong>CORDILLERA ADMINISTRATIVE REGION</strong><br>
        DICT Compound, Polo Field, Saint Joseph Village, Baguio City 2600
    </p>
</div>


<!-- Title -->
    <table class="no-border">
        <tr style="height: 25px;" ><td class="s3" colspan="26">PURCHASE REQUEST</td></tr>
        <tr style="height: 8px;"><td class="s4" colspan="26" margin-top: 0px; ></td></tr>
    </table>

<!-- Entity Name & Fund Cluster -->
<table class="no-border" style="width:100%; font-size:8px; table-layout:fixed;">
    <tr style="white-space:nowrap;">
        <td style="width:65%;">
            <span class="inline-label">Entity Name:</span>
            <span class="underline">Department of Information and Communications Technology - CAR</span>
        </td>
        <td style="width:35%;">
            <span class="inline-label">Fund Cluster:</span>
            <span class="underline">{{ $procurement->fundCluster->name ?? '101 - Regular Agency Fund' }}</span>
        </td>
    </tr>
</table>

<!-- Header Row -->
<table>
    <tr>
        <td style="width:32%;">Office/Section : <span class="blue">
            @php
                $officeSection = $procurement->office_section ?? '';
                if (str_contains($officeSection, 'Admin and Finance Division')) {
                    echo 'AFD';
                } elseif (str_contains($officeSection, 'Technical Operations Division')) {
                    echo 'TOD';
                } else {
                    echo $officeSection;
                }
            @endphp
        </span></td>
        <td style="width:38%;">Purchase Request Number : <span class="blue">{{ $procurement->procurement_id }}</span></td>
        <td style="width:30%;">Date : <span class="blue">{{ $procurement->created_at->format('d-M-Y') }}</span></td>
    </tr>
    <tr>
        <td style="width:32%;"></td>
        <td style="width:38%;">Responsibility Center Code :</td>
        <td style="width:30%;"></td>
    </tr>
</table>

<!-- Items Table -->
<table style="margin-top:0;">
    <thead>
        <tr>
            <th style="width:8%;">{{ $procurement->basis === 'lot' ? 'Lot No.' : 'Item No.' }}</th>
            <th style="width:10%;">Unit</th>
            <th style="width:42%;">{{ $procurement->basis === 'lot' ? 'Lot Description' : 'Item Description' }}</th>
            <th style="width:10%;">Quantity</th>
            <th style="width:15%;">Unit Cost</th>
            <th style="width:15%;">Total Cost</th>
        </tr>
    </thead>
    <tbody>
        @php $grandTotal = 0; @endphp
        @foreach ($procurement->items as $i => $item)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td class="center">{{ $item->unit }}</td>
                <td>{{ $item->item_description }}</td>
                <td class="center">{{ $item->quantity }}</td>
                <td class="right">₱{{ number_format($item->unit_cost, 2) }}</td>
                <td class="right">₱{{ number_format($item->total_cost, 2) }}</td>
            </tr>
            @php $grandTotal += $item->total_cost; @endphp
        @endforeach
        <!-- Nothing Follows row -->
        <tr>
            <td class="center"></td>
            <td class="center"></td>
            <td class="center">~*~ NOTHING FOLLOWS ~*~</td>
            <td class="center"></td>
            <td class="center"></td>
            <td class="center"></td>
        </tr>
        <!-- Total row -->
        <tr>
            <td class="center"></td>
            <td class="center"></td>
            <td class="center"></td>
            <td class="center"></td>
            <td class="right"><strong>TOTAL</strong></td>
            <td class="right"><strong>₱{{ number_format($grandTotal, 2) }}</strong></td>
        </tr>
    </tbody>
</table>

<!-- Purpose -->
<table style="margin-top:0; border-collapse:collapse; width:100%;">
    <tr>
        <td colspan="6" class="highlight-yellow">
            <i>Purpose: {{ $procurement->title ?? 'Procurement of supplies for the replenishment of stocks used for the ICT Industry Development Programs' }}</i>
        </td>
    </tr>
</table>

@php
    // Get approvals for PR module
    $prApprovals = $procurement->approvals()
        ->where('module', 'purchase_request')
        ->with(['employee.certificate', 'employee'])
        ->orderBy('sequence')
        ->get();

// use approvers passed from controller 
$firstApprover = $firstApprover ?? null;
$secondApprover = $secondApprover ?? null;




    $requestedByApproval = $procurement->requester ? (object) [
        'employee' => $procurement->requester,
        'signature' => $prApprovals->where('employee_id', $procurement->requested_by)->first()?->signature
    ] : null;
@endphp

<!-- Requested / Approved -->
<table style="width:100%; border:1px solid rgb(246, 246, 246); border-collapse:collapse; margin-top:0px; font-size:8px;">
    <tr>
        <!-- Requested By -->
        <td style="width:50%; padding:6px; vertical-align:top; border-right:none;">
            <span class="italic-label">Requested By:</span>
            
            @if($requestedByApproval && $requestedByApproval->signature)
                <div class="signature-container">
                    <img src="data:image/png;base64,{{ $requestedByApproval->signature }}" 
                         class="signature-img"
                         alt="Signature">
                </div>
            @else
                <div style="height:30px;"></div>
            @endif
            
            <!-- Name -->
            <div style="text-align:center; margin-top:2px;">
                <div style="border-bottom:1px solid black; display:inline-block; min-width:280px; padding:2px 5px;">
                    <strong>{{ $requestedByApproval?->employee?->full_name ?? 'Not set' }}</strong>
                </div>
            </div>
            
            <!-- Designation -->
            <div style="margin-top:3px; text-align:center; font-style:italic; font-size:8px;">
                {{ $requestedByApproval?->employee?->designation ?? 'Designation not set' }}
            </div>
        </td>
        
        <!-- Approved By -->
        <!-- Approved By (1st Approver - Budget Officer) -->
<td style="width:50%; padding:6px; vertical-align:top; border-left:none;">
    <span class="italic-label">Approved By:</span>
    
    @if($firstApprover && $firstApprover->signature)
        <div class="signature-container">
            <img src="data:image/png;base64,{{ $firstApprover->signature }}" 
                 class="signature-img"
                 alt="Signature">
        </div>
    @else
        <div style="height:30px;"></div>
    @endif
    
    <!-- Name -->
    <div style="text-align:center; margin-top:2px;">
        <div style="border-bottom:1px solid black; display:inline-block; min-width:280px; padding:2px 5px;">
            <strong>{{ $firstApprover?->employee?->full_name ?? 'Not set' }}</strong>
        </div>
    </div>
    
    <!-- Designation -->
    <div style="margin-top:3px; text-align:center; font-style:italic;">
    {{ $firstApprover->designation ?? 'Designation not set' }}
</div>

</td>


    </tr>
</table>

<!-- Funds Available -->
<div style="margin-top:40px; text-align:center;">
    <div class="purple" style="margin-bottom:5px;">FUNDS AVAILABLE</div>
    
    @if($secondApprover && $secondApprover->signature)
    <div class="signature-container">
        <img src="data:image/png;base64,{{ $secondApprover->signature }}" 
             class="signature-img"
             alt="Signature">
    </div>
@else
    <div style="height:30px;"></div>
@endif

    
    <!-- Name -->
    <div style="margin-top:2px;">
        <strong>{{ $secondApprover?->employee?->full_name ?? 'Not set' }}</strong>
    </div>
    
    <div style="margin-top:2px;">
    <i>{{ $secondApprover->designation ?? 'Designation not set' }}</i>
</div>

    </div>
</div>

</body>
</html>