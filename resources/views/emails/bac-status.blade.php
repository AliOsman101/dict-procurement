<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BAC Resolution {{ $status }}</title>

    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; color: #333; padding: 20px; }
        .container { background: #fff; padding: 25px; border-radius: 8px;
                     max-width: 650px; margin: 0 auto;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: {{ $status === 'Approved' ? '#28a745' : '#dc3545' }}; }
        .details { background: #f1f3f5; padding: 15px; border-radius: 6px; margin: 20px 0; }
        .button { background: #007BFF; color: #fff !important; padding: 10px 20px;
                 border-radius: 6px; text-decoration: none; display: inline-block; margin-top: 15px; }
        .footer { margin-top: 25px; font-size: 13px; color: #666; }
    </style>
</head>

<body>
    <div class="container">
        <h2>BAC Resolution {{ ucfirst($status) }}</h2>

        <p>Hello,</p>

        <p>
            The BAC Resolution has been
            <strong style="color: {{ $status === 'Approved' ? '#28a745' : '#dc3545' }}">
                {{ strtoupper($status) }}
            </strong>
            by <strong>{{ $approver->employee->full_name ?? $approver->name ?? 'Approver' }}</strong>
.
        </p>

        @if($remarks)
            <p><strong>Remarks:</strong> {{ $remarks }}</p>
        @endif

        <div class="details">
            <p>
                <strong>BAC Resolution No.:</strong> {{ $procurement->procurement_id }}<br>
                <strong>Title:</strong> {{ $procurement->title }}<br>
                <strong>Status:</strong> {{ $procurement->status }}<br>
                <strong>Requested By:</strong> {{ $procurement->parent?->requester?->full_name ?? 'N/A' }}
            </p>
        </div>

           <a href="{{ url('/admin/procurements/'.$procurement->parent_id) }}" class="button">
            View Purchase Request
        </a>

        <p class="footer">
            This is an automated message. Please do not reply.
        </p>
    </div>
</body>
</html>
