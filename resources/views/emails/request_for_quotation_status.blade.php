<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request for Quotation {{ $status }}</title>
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
            color: {{ $status === 'Approved' ? '#28a745' : '#dc3545' }};
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
        <h2>Request for Quotation {{ ucfirst($status) }}</h2>

        <p>Hello,</p>

        <p>
            The Request for Quotation has been 
            <strong style="color: {{ $status === 'Approved' ? '#28a745' : '#dc3545' }}">
                {{ strtoupper($status) }}
            </strong> 
            by <strong>{{ $approver->employee->full_name ?? $approver->name ?? 'Approver' }}</strong>.
        </p>

        <div class="details">
            <p>
                <strong>RFQ No.:</strong> {{ $procurement->procurement_id ?? 'N/A' }}<br>
                <strong>Title:</strong> {{ $procurement->title ?? 'N/A' }}<br>
                <strong>Status:</strong> {{ $procurement->status ?? 'N/A' }}<br>
            </p>
        </div>

        <p>
            Please log in to the <strong>DICT CAR Procurement System</strong> to review this Request for Quotation.
        </p>

        <a href="{{ url('/admin/procurements/'.$procurement->id) }}" class="button">
            View Request for Quotation
        </a>

        <p class="footer">
            This is an automated message from the DICT CAR Procurement System.<br>
            Please do not reply directly to this email.
        </p>
    </div>
</body>
</html>
