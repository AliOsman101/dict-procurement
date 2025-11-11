<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your DICT CAR PMS Account Has Been Approved!</title>
    <style>
        /* Basic styling for a professional email */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f8fa;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
        }
        .banner {
            width: 100%;
            max-width: 600px;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 24px;
            color: #333333;
        }
        p {
            font-size: 16px;
            color: #555555;
            line-height: 1.5;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 20px 0;
            background-color: #3490dc;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <img class="banner" src="https://cms-cdn.e.gov.ph/DICT/uploads/1e8731fe-3d70-45ec-9926-8d68e0b89382.png?t=1733210693181" alt="DICT Banner">
        <h1>Your DICT CAR PMS Account Has Been Approved!</h1>
        <p>Dear {{ $user->name }},</p>
        <p>We are pleased to inform you that your DICT CAR PMS account has been approved. You can now log in and take advantage of all the features of our system.</p>
        <p>
            <a href="{{ url('/') }}" class="button">Log In Now</a>
        </p>
        <p>If you have any questions, feel free to contact our support team.</p>
        <p>Regards,<br>DICT CAR</p>
    </div>
</body>
</html>
