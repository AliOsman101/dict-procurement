<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Next Approval Required</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            color: #333;
            padding: 20px;
        }
        .container {
            background: #ffffff;
            border-radius: 8px;
            padding: 25px;
            max-width: 650px;
            margin: 0 auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #007BFF;
            margin-bottom: 10px;
            font-size: 22px;
        }
        p {
            line-height: 1.6;
            font-size: 15px;
        }
        .details {
            background: #f1f3f5;
            padding: 12px 15px;
            border-radius: 6px;
            margin: 18px 0;
        }
        .details p {
            margin: 5px 0;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            margin-top: 20px;
            background-color: #007BFF;
            color: #ffffff !important;
            padding: 12px 22px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 15px;
        }
        .footer {
            margin-top: 25px;
            font-size: 12px;
            color: #666;
            text-align: center;
            line-height: 1.5;
        }
    </style>
</head>
<body>

    <div class="container">

        <h2>Approval Required</h2>

        <p>Hello {{ $nextApproverName }},</p>

        <p>
            You are the next assigned approver for the procurement:
            <strong>{{ $procurement->title }}</strong>.
        </p>

        <div class="details">
            <p><strong>Procurement ID:</strong> {{ $procurement->procurement_id }}</p>
            <p><strong>Module:</strong> {{ strtoupper(str_replace('_', ' ', $procurement->module)) }}</p>
            <p><strong>Approval Sequence:</strong> {{ $sequence }}</p>
            <p><strong>Status:</strong> {{ $procurement->status }}</p>
        </div>

        <p>
            Please review and take action by accessing the system using the link below.
        </p>

        <a href="{{ $actionUrl }}" class="button">Review & Approve</a>

        <p class="footer">
            This is an automated message from the DICT CAR Procurement System.<br>
            Please do not reply directly to this email.
        </p>

    </div>

</body>
</html>
