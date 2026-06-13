<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Support Request Reply</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f9; font-family:Arial, Helvetica, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9; padding:30px 0;">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background:#2563eb; padding:25px; text-align:center;">
                            <h1 style="margin:0; color:#ffffff; font-size:24px;">
                                Support Request Reply
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px;">

                            <p style="font-size:16px; color:#333;">
                                Hello <strong>{{ $support->user->first_name }}</strong>,
                            </p>

                            <p style="font-size:15px; color:#555; line-height:1.7;">
                                Thank you for contacting our support team. We have reviewed your request and provided a response below.
                            </p>

                            <!-- Request Details -->
                            <div style="background:#f8fafc; border-left:4px solid #2563eb; padding:15px; margin:25px 0;">
                                <h3 style="margin-top:0; color:#2563eb;">Request Title</h3>
                                <p style="margin:0; color:#444;">
                                    {{ $support->title }}
                                </p>
                            </div>

                            <!-- User Issue -->
                            <div style="background:#fff7ed; border-left:4px solid #f97316; padding:15px; margin-bottom:20px;">
                                <h3 style="margin-top:0; color:#ea580c;">Your Message</h3>
                                <p style="margin:0; color:#444;">
                                    {{ $support->explanation }}
                                </p>
                            </div>

                            <!-- Admin Reply -->
                            <div style="background:#ecfdf5; border-left:4px solid #10b981; padding:15px;">
                                <h3 style="margin-top:0; color:#059669;">Support Team Response</h3>
                                <p style="margin:0; color:#444;">
                                    {{ $support->reply }}
                                </p>
                            </div>

                            <p style="margin-top:30px; font-size:15px; color:#555; line-height:1.7;">
                                If you have any further questions, please feel free to contact us again.
                            </p>

                            <p style="font-size:15px; color:#555;">
                                Best Regards,<br>
                                <strong>Support Team</strong>
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f8fafc; text-align:center; padding:20px; font-size:13px; color:#888;">
                            © {{ date('Y') }} Support Team. All rights reserved.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>