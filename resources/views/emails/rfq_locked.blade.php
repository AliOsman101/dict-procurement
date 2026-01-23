<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request for Quotation Ready for Approval</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            color: #333;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 8px;
            padding: 25px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #007BFF;
            margin-bottom: 10px;
        }
        p {
            line-height: 1.6;
        }
        .details {
            background: #f1f3f5;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .button {
            display: inline-block;
            margin-top: 20px;
            background-color: #007BFF;
            color: #fff !important;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
        }
        
        .email-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
        }
        .email-footer img {
            height: 55px;
            width: auto;
            flex-shrink: 0;
            margin-right: 30px;   
        }
        .footer-separator {
            height: 50px;
            width: 1px;
            background-color: #ccc;
            flex-shrink: 0;
            margin-right: 30px;    
        }
        .footer-text {
            line-height: 1.6;
        }

        @media (max-width: 480px) {
            .email-footer {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            .footer-separator {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Request for Quotation Locked</h2>

        <p>Dear Approver,</p>

        <p>
            The following <strong>Request for Quotation (RFQ)</strong> has been <b>locked</b> and is now ready for your review and approval.
        </p>

        <div class="details">
            <p>
                <strong>RFQ No.:</strong> {{ $rfq->procurement_id }}<br>
                <strong>Title:</strong> {{ $rfq->title ?? 'N/A' }}<br>
                <strong>Requested By:</strong> {{ optional($rfq->parent?->requester)->full_name ?? 'N/A' }}<br>
                <strong>Status:</strong> {{ $rfq->status ?? 'Locked' }}
            </p>
        </div>

        <p>Please log in to the <strong>DICT CAR Procurement System</strong> to review and take action on this RFQ.</p>

        <a href="{{ $approvalLink }}" class="button">Review RFQ</a>

        <div class="email-footer">
            <img src="{{ $message->embed(public_path('images/dict-logo-only.png')) }}" alt="DICT Logo">
            <div class="footer-separator"></div>
            <div class="footer-text">
                This is an automated message from the DICT CAR Procurement System.<br>
                Please do not reply directly to this email.
            </div>
        </div>
    </div>
</body>
</html>
