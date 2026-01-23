<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Minutes of Opening {{ $status }}</title>
</head>
<body style="font-family: Arial; background: #f5f6f7; padding: 20px; color: #333;">
    <div style="background:white; padding: 25px; border-radius: 8px; max-width: 600px; margin:auto;">
        
        <h2 style="color:#0a58ca;">
            Minutes of Opening {{ $status }}
        </h2>

        <p>Hello {{ $employee->full_name ?? 'Employee' }},</p>

        <p>
            The Minutes of Opening for <strong>{{ $procurement->title }}</strong> 
            has been <strong>{{ strtolower($status) }}</strong>.
        </p>

        <div style="background: #eef1f3; padding: 12px; border-radius: 5px;">
            <strong>MO No.:</strong> {{ $procurement->procurement_id }} <br>
            <strong>Status:</strong> {{ $status }} <br>
            <strong>Remarks:</strong> {{ $remarks ?? 'N/A' }} <br>
        </div>

        <p>Please log in to the <strong>DICT CAR Procurement System</strong> for complete details.</p>

        @if($link)
            <a href="{{ $link }}" 
               style="display:inline-block; background:#0a58ca; color:white; padding:10px 18px;
               border-radius:5px; text-decoration:none; margin-top:20px;">
                View Minutes of Opening
            </a>
        @endif

        <br><br>

        <small style="color:#777;">
            This is an automated email. Please do not reply.
        </small>

    </div>
</body>
</html>
