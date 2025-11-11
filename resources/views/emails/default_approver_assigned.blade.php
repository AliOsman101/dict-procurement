<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Default Approver Assigned</title>
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
        <h2>Default Approver Assignment</h2>
        <p>Dear {{ $approver->employee->full_name }},</p>

        <p>
            You have been assigned as the <strong>Default Approver</strong> for the
            <strong>{{ $roleName }}</strong> module.
        </p>

        @if($approver->designation)
            <p><strong>Designation:</strong> {{ $approver->designation }}</p>
        @endif

        @if($approver->office_section)
            <p><strong>Office/Section:</strong> {{ $approver->office_section }}</p>
        @endif


        <a href="{{ url('/') }}" class="button">Go to Procurement System</a>

        <p class="footer">
            This is an automated message from the DICT CAR Procurement System.<br>
            Please do not reply directly to this email.
        </p>
    </div>
</body>
</html>
