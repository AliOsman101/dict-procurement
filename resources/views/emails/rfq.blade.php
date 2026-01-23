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

                <!-- Main Container -->
                <table width="560" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; border: 1px solid #e0e0e0; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: left;">
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px 40px 10px 40px;">

                            <h2 style="color: #003366; font-size: 19px; font-weight: 700; margin: 0 0 12px 0; text-align: center;">
                                Request for Quotation
                            </h2>

                            <p style="font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
                                Dear Supplier,
                            </p>

                            <!-- SINGLE Correct Email Body -->
                            <p style="font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
                                {!! nl2br(e($emailBody)) !!}
                            </p>

                            <!-- Confirm RFQ Received Button -->
                            @if (!empty($receiveUrl))
                                <p style="text-align: center; margin: 20px 0;">
                                    <a href="{{ $receiveUrl }}"
                                        style="background-color: #003366; color: #ffffff; padding: 10px 18px; 
                                            border-radius: 6px; font-size: 14px; text-decoration: none; 
                                            display: inline-block;">
                                        Confirm RFQ Received
                                    </a>
                                </p>

                                <p style="font-size: 12px; color: #777; text-align: center; margin: 0 0 18px 0;">
                                    Clicking this button will confirm that you have received the RFQ and will notify the BAC personnel who sent it.
                                </p>
                            @endif

                            <p style="font-size: 14px; line-height: 1.6; margin: 0;">
                                Thank you for your participation.
                            </p>

                            <hr style="border: none; border-top: 1px solid #e2e2e2; margin: 18px 0; width: 100%;">

                            <p style="font-size: 12px; color: #777; line-height: 1.5; margin: 0; text-align: center;">
                                <strong>Contact details for DICT Baguio (CAR)</strong>.<br>
                                Department of Information and Communications Technology
                            </p>
                        </td>
                    </tr>

                    <!-- Footer with Logo and Contact Info -->
                    <tr>
                        <td style="padding: 20px 40px 30px 40px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>

                                    <td width="110" valign="top" style="padding-right: 20px;">
                                        <img src="{{ $message->embed(public_path('images/dict-logo-only.png')) }}" 
                                             alt="DICT Logo" 
                                             width="90" 
                                             style="display: block; width: 90px; height: auto;">
                                    </td>

                                    <td width="1" valign="top" style="background-color: #d0d0d0; padding: 0; width: 1px;"></td>

                                    <td valign="top" style="font-size: 12px; line-height: 1.7; color: #555; padding-left: 20px;">
                                        <p style="margin: 0 0 4px 0;">
                                            <strong>General Email:</strong> 
                                            <a href="mailto:car@dict.gov.ph" style="color: #003366; text-decoration: none;">car@dict.gov.ph</a>
                                        </p>
                                        <p style="margin: 0 0 4px 0;">
                                            <strong>Phone:</strong> +63 74 442 4616
                                        </p>
                                        <p style="margin: 0 0 4px 0;">
                                            <strong>Facebook:</strong> 
                                            <a href="https://www.facebook.com/dictcar" target="_blank" style="color: #003366; text-decoration: none;">DICT Cordillera Administrative Region</a>
                                        </p>
                                        <p style="margin: 0;">
                                            <strong>Address:</strong> DICT Compound, Polo Field, St. Joseph Village, Baguio City
                                        </p>
                                    </td>

                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
