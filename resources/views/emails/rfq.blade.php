<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request for Quotation</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f6f8; font-family: 'Segoe UI', Arial, sans-serif; color: #333;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f6f8; padding: 40px 0;">
        <tr>
            <td align="center">
                <!-- Container -->
                <table width="560" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; border: 1px solid #e0e0e0; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: left;">
                    
                    <!-- Banner -->
                    <tr>
                        <td style="text-align: center; padding: 20px 0 10px 0;">
                            <img src="{{ $message->embed(public_path('images/dict-banner.png')) }}" 
                                 alt="DICT Banner" 
                                 width="320" 
                                 style="display: block; margin: 0 auto; width: 60%; max-width: 320px; height: auto;">
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 20px 40px 10px 40px;">
                            <h2 style="color: #003366; font-size: 19px; font-weight: 700; margin: 0 0 12px 0; text-align: center;">
                                Request for Quotation
                            </h2>

                            <p style="font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
                                Dear Supplier,
                            </p>

                            <p style="font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
                                {!! nl2br(e($emailBody)) !!}
                            </p>

                            <p style="font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
                                Please see the attached RFQ document for your reference.
                            </p>

                            <p style="font-size: 14px; line-height: 1.6; margin: 0;">
                                Thank you for your participation.
                            </p>

                            <hr style="border: none; border-top: 1px solid #e2e2e2; margin: 18px 0; width: 100%;">

                            <p style="font-size: 12px; color: #777; line-height: 1.5; margin: 0; text-align: center;">
                                This is an automated message from the 
                                <strong>DICT Procurement System</strong>.<br>
                                For inquiries, contact: 
                                <a href="mailto:car.bac@dict.gov.ph" style="color: #003366; text-decoration: none;">car.bac@dict.gov.ph</a><br>
                                Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="text-align: center; padding: 20px 0 30px 0;">
                            <img src="{{ $message->embed(public_path('images/footer.png')) }}" 
                                 alt="DICT Footer" 
                                 width="340" 
                                 style="display: block; margin: 0 auto; width: 60%; max-width: 340px; height: auto;">
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>