<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order {{ $status }}</title>
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
        .remarks {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .remarks strong {
            color: #856404;
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
        <h2>Purchase Order {{ ucfirst($status) }}</h2>

        <p>Hello,</p>

        <p>
            The Purchase Order has been
            <strong style="color: {{ $status === 'Approved' ? '#28a745' : '#dc3545' }}">
                {{ strtoupper($status) }}
            </strong>
            by <strong>{{ $approver->employee->full_name ?? 'Approver' }}</strong>.
        </p>

        <div class="details">
            <p>
                <strong>PO No.:</strong> {{ $procurement->procurement_id ?? 'N/A' }}<br>
                <strong>Title:</strong> {{ $procurement->title ?? 'N/A' }}<br>
                <strong>Status:</strong> {{ $procurement->status ?? 'N/A' }}<br>
                <strong>Requested By:</strong> 
                    {{ $procurement->parent?->children()->where('module','purchase_request')->first()?->requester?->full_name ?? 'N/A' }}
            </p>
        </div>

        @if($status === 'Rejected' && $remarks)
        <div class="remarks">
            <p><strong>Rejection Remarks:</strong></p>
            <p>{{ $remarks }}</p>
        </div>
        @endif

        <p>
            Please log in to the <strong>DICT CAR Procurement System</strong> to review this Purchase Order.
        </p>

        <a href="{{ url('/admin/procurements/'.$procurement->id) }}" class="button">
            View Purchase Order
        </a>

        <p class="footer">
            This is an automated message from the DICT CAR Procurement System.<br>
            Please do not reply directly to this email.
        </p>
    </div>
</body>
</html>
