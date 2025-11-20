<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Request for Quotation</title>
    <style>
        body { font-family: 'Play', 'DejaVu Sans', sans-serif; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; margin-left: 10px; }
        th, td { padding: 4px; vertical-align: middle; }
        .s0 { text-align: center; font-style: italic; vertical-align: top; }
        .s1 { text-align: center; font-size: 6pt; vertical-align: middle; }
        .s2 { text-align: center; border-bottom: 1px solid transparent; }
        .s4, .s5, .s6, .s7, .s8, .s9, .s10 { border-bottom: 1px solid #000000; }
        .s6, .s7, .s9, .s10 { border-right: 1px solid #000000; }
        .s7, .s9, .s10 { font-weight: bold; color: #0000ff; }
        .s11 { background-color: #ffffff; }
        .s12 { font-style: italic; }
        .s13 { font-weight: bold; }
        .s14 { white-space: normal; overflow: hidden; word-wrap: break-word; }
        .s15, .s16, .s17, .s18 { border-left: none; }
        .s15, .s18 { border-right: none; }
        .s19 { font-weight: bold; }
        .s20, .s22 { border-bottom: 2px solid #000000; border-right: 2px solid #000000; }
        .s21 { text-align: center; }
        .s3 { text-align: center; font-weight: bold; font-size: 10pt; background-color: #ffffc8; }
        .s4 {border-bottom: 1px solid #000;}
        .s25 { text-align: center; font-style: italic; }
        .s26 { text-align: left; }
        .s27 { text-align: center; vertical-align: top; }
        .s28 { text-align: left; vertical-align: top; white-space: normal; overflow: hidden; word-wrap: break-word; }
        .s29 { border-bottom: 1px solid #000000; }
        .s30 { border-bottom: 1px solid #000000; border-right: 1px solid #000000; }
        .s31 { border-bottom: 1px solid #000000; border-right: 1px solid #000000; white-space: normal; overflow: hidden; word-wrap: break-word; }
        .s32 { text-align: left; vertical-align: bottom; }
        .s33 { text-align: center; font-weight: bold; background-color: #ffffc8; }
        .s34 { text-align: left; font-weight: bold; vertical-align: top; white-space: normal; overflow: hidden; word-wrap: break-word; }
        .s35 { vertical-align: bottom; }
        .s36 { border-left: none; }
        .s37 { border-left: none; border-bottom: 1px solid #000000; font-weight: bold; text-align: center; }
        .s38 { font-weight: bold; font-style: italic; }
        .s39 { font-family: 'Play', 'DejaVu Sans', sans-serif; font-size: 11pt; vertical-align: bottom; }
        .highlight-yellow { background-color: #fff799; font-weight: bold; text-align: center; font-family: "Arial Black", sans-serif; }
        .purple { color: purple; font-weight: bold; text-align: center; }
        .blue { color: blue; font-weight: bold; }
        .no-border td { border: none !important; }
        .underline { border-bottom: 1px solid black; display: inline-block; min-width: 180px; text-indent: 3px; }
        .inline-label { font-weight: bold; white-space: nowrap; }
        i { font-style: italic; }
        .softmerge-inner { position: relative; }
        
        /* Signature styling - Consistent with PR, AOQ, BAC, PO */
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

                    <div style="font-size:11px; color: #05339C; text-align:center;  font-weight:bold; letter-spacing:0.3px; line-height:1.3;">
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
        <tr style="height: 25px;" ><td class="s3" colspan="26">REQUEST FOR QUOTATION</td></tr>
        <tr style="height: 8px;"><td class="s4" colspan="26" margin-top: 0px; ></td></tr>
    </table>

    <!-- Details -->
    @php
        $pr = $procurement->parent ? $procurement->parent->children()->where('module', 'purchase_request')->first() : null;
        $prNo = $pr ? $pr->procurement_id : 'Not set';
        $abc = $pr ? $pr->grand_total : 0;
        $procurementType = $procurement->procurement_type === 'small_value_procurement' ? 'Small Value Procurement' : 'Public Bidding';
        $rfqNo = $procurement->procurement_id ?? 'Not set';
    @endphp
    <table style="border: 1px solid black; border-collapse: collapse; margin-left: 10px; margin-bottom: 15px; margin-top: 15px;">
        <tr style="height: 19px;">
            <td class="s5" colspan="7">Title/ Purpose:</td>
            <td class="s6 blue" colspan="19">{{ $procurement->title }}</td>
        </tr>
        <tr style="height: 19px;">
            <td class="s5" colspan="7">ABC:</td>
            <td class="s7" colspan="4">₱{{ number_format($abc, 2) }}</td>
            <td class="s8" colspan="4">End User :</td>
            <td class="s9" colspan="11">
                {{ $procurement->office_section == 'DICT CAR - Technical Operations Division' ? 'DICT CAR - Technical Operations Division' : 'DICT CAR - Admin and Finance Division' }}
            </td>
        </tr>
        <tr style="height: 19px;">
            <td class="s5" colspan="7">Purchase Request No. :</td>
            <td class="s7" colspan="4">{{ $prNo }}</td>
            <td class="s8" colspan="4">Mode of Procurement :</td>
            <td class="s10" colspan="11">{{ $procurementType }}</td>
        </tr>
        <tr style="height: 19px;">
            <td class="s5" colspan="7">Request for Quotation No. :</td>
            <td class="s7" colspan="4">{{ $rfqNo }}</td>
            <td class="s8" colspan="4">Delivery Period (CD):</td>
            <td class="s9" colspan="11">
                @if($procurement->delivery_mode === 'days' && $procurement->delivery_value)
                    Within {{ $procurement->delivery_value }} calendar days upon receipt of Purchase Order
                @elseif($procurement->delivery_mode === 'date' && $procurement->delivery_value)
                    {{ \Carbon\Carbon::parse($procurement->delivery_value)->format('F j, Y') }}
                @else
                    Not set
                @endif
            </td>
        </tr>
    </table>

    <!-- Notice -->
    <table>
        <tr style="height: 19px;"><td class="s13" colspan="26" style="padding-bottom: 10px;">NOTICE TO ALL SERVICE PROVIDERS AND SUPPLIERS:</td></tr>
        <tr style="height: 19px;"><td class="s11" colspan="26"></td></tr>
        <tr style="height: 19px;">
            <td class="s14" colspan="26">
            Please quote your lowest price on the items/s listed below, subject to the conditions stated below, 
            stating the shortest time of delivery and submit your quotation duly signed by you or your authorized 
            representative not later than 
            <span style="font-weight: bold; color: #0000ff;">
                {{ $procurement->deadline_date 
                    ? \Carbon\Carbon::parse($procurement->deadline_date)->format('F j, Y \a\t g:i A') 
                    : 'Not set' }}
            </span> 
            duly sealed in an envelope address to:
        </td>
        </tr>
        <tr style="height: 19px;"><td class="s11" colspan="4"></td><td class="s15" colspan="1"><div class="softmerge-inner" style="width: 238px;"><strong>ENGR. KATHERINE FAITH B. AGUILAR</strong></div></td><td class="s16" colspan="5"></td><td class="s17" colspan="2"></td><td class="s11" colspan="14"></td></tr>
        <tr style="height: 19px;"><td class="s11" colspan="4"></td><td class="s18" colspan="1"><div class="softmerge-inner" style="width: 130px;">Head, BAC Secretariat</div></td><td class="s16" colspan="4"></td><td class="s17" colspan="2"></td><td class="s11" colspan="15"></td></tr>
        <tr style="height: 19px;"><td class="s11" colspan="4"></td><td class="s18" colspan="1"><div class="softmerge-inner" style="width: 238px;">DICT-CAR, St. Joseph Village, Baguio City</div></td><td class="s16" colspan="5"></td><td class="s17" colspan="2"></td><td class="s11" colspan="14"></td></tr>
        <tr style="height: 19px;"><td class="s11" colspan="4"></td><td class="s18" colspan="1"><div class="softmerge-inner" style="width: 202px;">Email Address: car.bac@dict.gov.ph</div></td><td class="s16" colspan="4"></td><td class="s17" colspan="2"></td><td class="s11" colspan="15"></td></tr>
        <tr style="height: 19px;"><td class="s11" colspan="26"></td></tr>
    </table>

    <!-- Submit Documents -->
    @php
        $categoryName = $procurement->category ? $procurement->category->name : '';
    @endphp
    <table style="font-size: 8pt; margin-left: 10px; margin-top: 15px;">
        <tr style="height: 19px;"><td class="s19" colspan="26">Submit your proposal along with the following documents:</td></tr>
        <tr style="height: 19px;"><td class="s20" style="border-left: 2px solid #000000; border-top: 2px solid #000000;">✓</td><td class="s21">1.</td><td class="s11" colspan="24" style="font-size: 7.7pt;">Latest Business/Mayor's Permit issued by the city or municipality where the principal place of business of the bidder is located;</td></tr>
        <tr style="height: 19px;">
            <td class="s22" style="border-left: 2px solid #000000;">
                @if($categoryName == 'Consulting Services')
                    ✓
                @endif
            </td>
            <td class="s21">2.</td>
            <td class="s11" colspan="24">Professional License/CV (for Consulting Services)</td>
        </tr>
        <tr style="height: 19px;"><td class="s20" style="border-left: 2px solid #000000;">✓</td><td class="s21">3.</td><td class="s11" colspan="24">PhilGEPS Certificate of Registration; or Screenshot of PhilGEPS Registration Information;</td></tr>
        <tr style="height: 19px;">
            <td class="s22" style="border-left: 2px solid #000000;">
                @if($categoryName == 'Infrastructure Projects')
                    ✓
                @endif
            </td>
            <td class="s21">4.</td>
            <td class="s11" colspan="24">PCAB License (for Infrastructure Projects)</td>
        </tr>
        <tr style="height: 19px;">
            <td class="{{ $abc > 500000 ? 's20' : 's22' }}" style="border-left: 2px solid #000000;">
                @if($abc > 500000)
                    ✓
                @endif
            </td>
            <td class="s21">5.</td>
            <td class="s11" colspan="24">Latest Income/Business Tax Return (for ABCs above Php 500,000.00); and</td>
        </tr>
        <tr style="height: 19px;">
            <td class="s22" style="border-left: 2px solid #000000;">
                @if(in_array($categoryName, ['Catering Services', 'Printing Services']))
                    ✓
                @endif
            </td>
            <td class="s21">6.</td>
            <td class="s11" colspan="24">Terms and Conditions for Contract of Service (catering) / Technical Specifications and Requirements (printing service).</td>
        </tr>
        <tr style="height: 19px;"><td class="s20" style="border-left: 2px solid #000000;">✓</td><td class="s21">7.</td><td class="s14" colspan="24">Notarized Omnibus Sworn Statement using GPPB-Prescribed Format.</td></tr>
        <tr style="height: 19px;"><td class="s12" colspan="2"></td><td class="s23" colspan="24">Proof of authorization shall be a duly notarized Secretary's Certificate, Board/Partnership Resolution, or Special Power of Attorney, whichever is applicable, in case of a corporation, partnership, or cooperative, or joint venture; or a duly notarized Special Power of Attorney in case of a sole proprietorship, giving full power and authority to its officer to sign the OSS and do acts to represent the Bidder.</td></tr>
        <tr style="height: 19px;"><td class="s12" colspan="26"></td></tr>
    </table>

    <!-- BAC Chairperson Line with Signature -->
    @php
        $rfqApprovals = $procurement->approvals()
            ->where('module', 'request_for_quotation')
            ->orderBy('sequence')
            ->with('employee.certificate')
            ->get();
        
        $bacChairpersonApproval = $rfqApprovals->where('designation', 'BAC Chairperson')
            ->first();
    @endphp
    <table style="width: 100%; margin-top: 15px; margin-bottom: 10px; border-collapse: collapse;">
        <tr>
            <td style="text-align: left; padding-top: 10px;">
                <!-- Signature Image - Centered above the name line -->
                @if($bacChairpersonApproval && $bacChairpersonApproval->signature)
                    <div class="signature-container" style="margin-left:50px; text-align: center; width: 220px;">
                        <img src="data:image/png;base64,{{ $bacChairpersonApproval->signature }}" 
                            class="signature-img"
                            alt="Signature">
                    </div>
                @else
                    <div style="height:30px; margin-bottom:5px; margin-left:50px;"></div>
                @endif
                
                <!-- Name with underline - keep original positioning -->
                <div style="font-weight: bold; display: inline-block; width: 310px; text-align: center;">
                    {{ $bacChairpersonApproval?->employee?->full_name ?? 'Not set' }}
                </div>
                <div style="border-top: 1px solid black; width: 220px; margin-left: 50px;"></div>
                <div style="margin-top: 3px; font-style: italic; margin-left: 115px;">BAC Chairperson</div>
            </td>
        </tr>
    </table>

    <!-- Instructions -->
    <table style="width:100%; border-collapse: collapse; font-size: 8pt; padding-top:13px;">
        <tr>
            <td colspan="26" style="font-weight: bold; padding-bottom: 5px;">INSTRUCTIONS:</td>
        </tr>
        <tr>
            <td style="width: 2%; vertical-align: top;">1.</td>
            <td colspan="25" style="text-align: justify;">The bidder shall provide correct and accurate information in this form.</td>
        </tr>
        <tr>
            <td style="vertical-align: top;">2.</td>
            <td colspan="25" style="text-align: justify;">The bidder shall submit this form duly accomplished and signed by the company's authorized representative.</td>
        </tr>
        <tr>
            <td style="vertical-align: top;">3.</td>
            <td colspan="25" style="text-align: justify;">Do not alter the contents of this form in any way.</td>
        </tr>
        <tr>
            <td style="vertical-align: top;">4.</td>
            <td colspan="25" style="text-align: justify;">All technical specifications are mandatory. Failure to comply with any of the mandatory requirements will disqualify your quotation.</td>
        </tr>
        <tr>
            <td style="vertical-align: top;">5.</td>
            <td colspan="25" style="text-align: justify;">
                <span style="font-weight: bold;">Bidders must state their compliance</span> in the 
                <span style="font-weight: bold;">"Statement of Compliance"</span> against each of the individual parameters of each Specification in the Item Description.
            </td>
        </tr>
        <tr>
            <td style="vertical-align: top;">6.</td>
            <td colspan="25" style="text-align: justify;">Please do not leave any blank items. Instead, indicate <span style="font-weight: bold;">"NA"</span> if the item is not available.</td>
        </tr>
        <tr>
            <td style="vertical-align: top;">7.</td>
            <td colspan="25" style="text-align: justify;">Failure to follow instructions will disqualify your entire quotation.</td>
        </tr>
        <tr>
            <td style="vertical-align: top;">8.</td>
            <td colspan="25" style="text-align: justify;">Quotation and other documents required may be sent electronically to car.bac@dict.gov.ph as advance copy.</td>
        </tr>
    </table>

    <!-- Salutation and Submission -->
    <table>
        <tr style="height: 19px;"><td class="s11" colspan="26" style="padding-top: 25px;">Sir/Madam:</td></tr>
        <tr style="height: 19px;">
            <td class="s14" colspan="26" style="padding-bottom: 10px;">After having carefully read and accepted the Terms and Conditions in this Request for Quotation, I/We submit our quotation/s for the item/s as follows:</td>
        </tr>
    </table>

    <!-- Items Table -->
    @php
        $items = $pr ? $pr->items()->get() : collect();
    @endphp
    <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; font-size: 8pt; text-align: center;">
        <thead>
            <tr>
                <th style="border: 1px solid black; width: 5%; font-weight: normal;" rowspan="2">{{ $pr && $pr->basis === 'lot' ? 'Lot No.' : 'Item No.' }}</th>
                <th style="border: 1px solid black; width: 5%; font-weight: normal;" rowspan="2">Unit</th>
                <th style="border: 1px solid black; width: 5%; font-weight: normal;" rowspan="2">Qty</th>
                <th style="border: 1px solid black; width: 30%; text-align: left; padding: 4px; text-align: center; font-weight: normal;" rowspan="2">
                    {{ $pr && $pr->basis === 'lot' ? 'Lot Description' : 'Item Description' }} <br>(Agency's Minimum Technical Specifications & Requirements)
                </th>
                <th style="border: 1px solid black; width: 8%; font-weight: normal;" rowspan="2">Total ABC<br>per {{ $pr && $pr->basis === 'lot' ? 'Lot' : 'Item' }}</th>
                <th style="border: 1px solid black; width: 10%; font-weight: normal;" colspan="2">Statement of Compliance</th>
                <th style="border: 1px solid black; width: 15%; font-weight: normal;" rowspan="2">Brand Name, Model,<br>and Other Remarks</th>
                <th style="border: 1px solid black; width: 8%; font-weight: normal;" rowspan="2">Unit Value</th>
                <th style="border: 1px solid black; width: 8%; font-weight: normal;" rowspan="2">Total Value</th>
            </tr>
            <tr>
                <th style="border: 1px solid black; width: 5%; font-weight: normal;">Yes</th>
                <th style="border: 1px solid black; width: 5%; font-weight: normal;">No</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
                <tr>
                    <td style="border: 1px solid black;">{{ $index + 1 }}</td>
                    <td style="border: 1px solid black;">{{ $item->unit }}</td>
                    <td style="border: 1px solid black;">{{ $item->quantity }}</td>
                    <td style="border: 1px solid black; text-align: left; padding: 4px;">{{ $item->item_description }}</td>
                    <td style="border: 1px solid black;">₱{{ number_format($item->total_cost, 2) }}</td>
                    <td style="border: 1px solid black; text-align: center;">
                        <input type="checkbox">
                    </td>
                    <td style="border: 1px solid black; text-align: center;">
                        <input type="checkbox">
                    </td>
                    <td style="border: 1px solid black;"></td>
                    <td style="border: 1px solid black;"></td>
                    <td style="border: 1px solid black;"></td>
                </tr>
            @endforeach
            <tr>
                <td colspan="10" style="border: 1px solid black; text-align: center; font-style: italic; padding: 4px;">
                    ~*~ NOTHING FOLLOWS ~*~
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Terms and Conditions -->
    <table>
        <tr style="height: 19px;"><td class="s33" colspan="26">TERMS AND CONDITIONS</td></tr>
        <tr style="height: 19px;"><td class="s27">1.</td><td class="s28" colspan="25">Price quotation/s, to be dominated in Philippine peso shall include all taxes, duties, and/or levies payable.</td></tr>
        <tr style="height: 19px;"><td class="s27">2.</td><td class="s28" colspan="25">Bids should be valid for at least 60 calendar days from the deadline of submission.</td></tr>
        <tr style="height: 19px;"><td class="s27">3.</td><td class="s28" colspan="25">Service providers or suppliers shall provide correct and accurate technical specifications, brand name, and product model in this form.</td></tr>
        <tr style="height: 19px;"><td class="s27">4.</td><td class="s28" colspan="25">Quotations exceeding the Approved Budget for the Contract shall be rejected.</td></tr>
        <tr style="height: 19px;"><td class="s27">5.</td><td class="s34" colspan="25">Evaluation will be per {{ $pr && $pr->basis === 'lot' ? 'LOT' : 'ITEM' }} basis.</td></tr>
        <tr style="height: 19px;"><td class="s27">6.</td><td class="s28" colspan="25">Award of the contract shall be made to the lowest calculated and responsive quotation which complies with the minimum technical specifications and other terms and conditions stated herein.</td></tr>
        <tr style="height: 19px;"><td class="s27">7.</td><td class="s28" colspan="25">Any interlineations, erasures, or overwriting shall be valid only if they signed or initialed by you or any of your duly authorized representative/s.</td></tr>
        <tr style="height: 19px;"><td class="s27">8.</td><td class="s28" colspan="25">The item/s shall be delivered according to the requirements specified in the Technical Specifications or Terms of Reference.</td></tr>
        <tr style="height: 19px;"><td class="s27">9.</td><td class="s28" colspan="25">The DICT shall have the right to reject any or all offers and accept an offer as may be considered most advantageous to the Agency. Delivery of goods may be accepted, rejected, or reserved for acceptance (e.g item cannot be immediately inspected, needs further testing, etc.)</td></tr>
        <tr style="height: 19px;"><td class="s27">10.</td><td class="s28" colspan="25">In case two or more bidders are determined to have submitted the Lowest Calculated Quotation/Lowest Calculated and Responsive Quotation, the DICT shall adopt and employ "draw lots" or "toss - coin" as the tie-breaking method to finally determine the single winning provider in accordance with GPPB Circular 06-2005.</td></tr>
        <tr style="height: 19px;"><td class="s27">11.</td><td class="s28" colspan="25">A penalty shall impose of on-tenth (1/10) of one percent for every day of delay. The DICT may rescind the contract once the cumulative amount of liquidated damages reaches ten percent (10%) of the amount of the contract, without prejudice to other courses of action and remedies open to it.</td></tr>
        <tr style="height: 19px;"><td class="s27">12.</td><td class="s28" colspan="25">Terms of Payment: Payment shall be processed within 15 to 30 working days after inspection and acceptance of goods and services, and upon the submission of the required supporting documents, in accordance with existing government accounting rules and regulations. Please note that the corresponding bank transfer fee, if any, shall be chargeable to the bidder's account.</td></tr>
        <tr style="height: 19px;"><td class="s35" colspan="26"></td></tr>
    </table>

    <!-- Supplier Details -->
    <table style="margin-top: 20px; width: 100%; border-collapse: collapse; font-size: 8pt;">
        <!-- Heading -->
        <tr>
            <td colspan="26" style="font-weight: normal; padding-bottom: 20px;">
                Service Provider's/Supplier's Authorized Representative:
            </td>
        </tr>
        <!-- Submitted by + Date -->
        <tr style="height: 25px;">
            <!-- Submitted by -->
            <td colspan="5" style="vertical-align: bottom; font-weight: bold;">Submitted by :</td>
            <td colspan="7" style="vertical-align: bottom;">
                <div style="border-bottom: 1px solid black; height: 15px;"></div>
            </td>
            <td colspan="2"></td>
            <!-- Date -->
            <td colspan="7" style="vertical-align: bottom; font-weight: bold;">
                Date :
                <span style="display:inline-block; border-bottom: 1px solid black; width: 120px; height: 15px; margin-left: 5px;"></span>
            </td>
            <td colspan="5"></td>
        </tr>
        <!-- Signature label row -->
        <tr>
            <td colspan="5"></td>
            <td colspan="7" style="text-align: center; font-size: 7pt; font-style: italic;">
                Signature Over Printed Name
            </td>
            <td colspan="2"></td>
            <td colspan="5"></td>
            <td colspan="7"></td>
        </tr>
        <!-- Designation + TIN/VAT/NVAT -->
        <tr style="height: 25px;">
            <td colspan="5" style="font-weight: bold;">Designation :</td>
            <td colspan="7" style="border-bottom: 1px solid black;"></td>
            <td colspan="2"></td>
            <td colspan="6" style="font-weight: bold;">TIN: ( ) VAT ( ) NVAT :</td>
            <td colspan="6" style="border-bottom: 1px solid black;"></td>
        </tr>
        <!-- Business Name + PhilGEPS -->
        <tr style="height: 25px;">
            <td colspan="5" style="font-weight: bold;">Business Name :</td>
            <td colspan="7" style="border-bottom: 1px solid black;"></td>
            <td colspan="2"></td>
            <td colspan="6" style="font-weight: bold;">PhilGEPS Reg No. :</td>
            <td colspan="6" style="border-bottom: 1px solid black;"></td>
        </tr>
        <!-- Business Address + LBP Account Name -->
        <tr style="height: 25px;">
            <td colspan="5" style="font-weight: bold;">Business Address :</td>
            <td colspan="7" style="border-bottom: 1px solid black;"></td>
            <td colspan="2"></td>
            <td colspan="6" style="font-weight: bold;">LBP Account Name :</td>
            <td colspan="6" style="border-bottom: 1px solid black;"></td>
        </tr>
        <!-- Contact No. + LBP Account Number -->
        <tr style="height: 25px;">
            <td colspan="5" style="font-weight: bold;">Contact No. :</td>
            <td colspan="7" style="border-bottom: 1px solid black;"></td>
            <td colspan="2"></td>
            <td colspan="6" style="font-weight: bold;">LBP Account Number :</td>
            <td colspan="6" style="border-bottom: 1px solid black;"></td>
        </tr>
        <!-- Email Address -->
        <tr style="height: 25px;">
            <td colspan="5" style="font-weight: bold;">Email Address :</td>
            <td colspan="7" style="border-bottom: 1px solid black;"></td>
            <td colspan="14"></td>
        </tr>
    </table>
</body>
</html>