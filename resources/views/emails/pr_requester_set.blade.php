<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Requester Assigned</title>
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
        .footer {
            margin-top: 25px;
            font-size: 13px;
            color: #666;
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
    </style>
</head>
<body>
<div class="container">

    <h2>Requester Assignment</h2>

    <p>Dear {{ $procurement->requester?->full_name ?? 'Employee' }},</p>

    <p>
        You have been assigned as the <strong>Requester</strong> for the following
        <strong>Purchase Request (PR)</strong>.
    </p>

    <div class="details">
        <p>
            <strong>PR No.:</strong> {{ $prNumber }}<br>
            <strong>Title:</strong> {{ $title ?? 'N/A' }}<br>
            <strong>Assigned By:</strong> {{ $setBy }}<br>
        </p>
    </div>

    <a href="{{ url('/') }}" class="button">Go to Procurement System</a>

    <p class="footer">
        This is an automated message from the DICT CAR Procurement System.<br>
        Please do not reply directly to this email.
    </p>

</div>
</body>
</html>
