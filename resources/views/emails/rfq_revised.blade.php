<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFQ Revised</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f59e0b;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #fff;
            border: 1px solid #ddd;
            border-top: none;
            padding: 20px;
            border-radius: 0 0 5px 5px;
        }
        .details {
            background-color: #f9fafb;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #f59e0b;
        }
        .details p {
            margin: 8px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #f59e0b;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #d97706;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Request for Quotation Revised</h1>
    </div>
    <div class="content">
        <p>Dear Approver,</p>
        
        <p>The following Request for Quotation has been <strong>revised</strong> and is awaiting your review:</p>
        
        <div class="details">
            <p><strong>RFQ No.:</strong> {{ $procurementId }}</p>
            <p><strong>Title:</strong> {{ $title }}</p>
            <p><strong>Requested By:</strong> {{ $requestedBy }}</p>
        </div>
        
        <p>The RFQ has been updated and all approvals have been reset. Please review the revised RFQ at your earliest convenience.</p>
        
        <center>
            <a href="{{ $approvalLink }}" class="button">Review Revised RFQ</a>
        </center>
        
        <p>Thank you for your attention to this matter.</p>
        
        <p>Best regards,<br>
        DICT CAR Procurement System</p>
    </div>
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
    </div>
</body>
</html>