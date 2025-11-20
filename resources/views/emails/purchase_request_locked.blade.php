<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Request Locked</title>
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
        .footer {
            margin-top: 25px;
            font-size: 13px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Purchase Request Locked</h2>

        <p>Hello Approver,</p>

        <p>
            A new Purchase Request has been <strong>locked</strong> and is now ready for your review.
        </p>

        <div class="details">
            <p>
                <strong>PR No.:</strong> {{ $procurement->procurement_id }}<br>
                <strong>Title:</strong> {{ $procurement->title ?? 'N/A' }}<br>
                <strong>Status:</strong> {{ $procurement->status ?? 'Locked' }}<br>
                <strong>Requested By:</strong> {{ $procurement->requester->full_name ?? 'N/A' }}
            </p>
        </div>

        <p>Please log in to the <strong>DICT CAR Procurement System</strong> to review and take action on this request.</p>

        <a href="{{ $approvalLink }}" class="button">View Purchase Request</a>

        <p class="footer">
            This is an automated message from the DICT CAR Procurement System.<br>
            Please do not reply directly to this email.
        </p>
    </div>
</body>
</html>
