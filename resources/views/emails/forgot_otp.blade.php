<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password Reset OTP</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; margin: 0; -webkit-font-smoothing: antialiased;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #eef2f6;">
        
        <h2 style="color: #1f2937; margin-top: 0; font-size: 22px; font-weight: bold; border-bottom: 2px solid #EEF2F6; padding-bottom: 15px;">
            Password Reset Request
        </h2>
        
        <p style="color: #4b5563; font-size: 16px; line-height: 1.6; margin-top: 20px;">
            Hello <strong>{{ $user->first_name ?? 'User' }}</strong>,
        </p>
        <p style="color: #4b5563; font-size: 16px; line-height: 1.6;">
            We received a request to reset the password for your Kujuba account. Please use the verification code below to complete the process. 
        </p>
        
        <div style="text-align: center; margin: 35px 0;">
            <div style="display: inline-block; background: #EEF2F6; padding: 14px 30px; border-radius: 6px; border: 1px solid #e2e8f0;">
                <span style="font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #4F46E5;">
                    {{ $otp }}
                </span>
            </div>
            <p style="color: #9ca3af; font-size: 13px; margin-top: 10px; font-style: italic;">
                This OTP is secure and valid for 5 minutes only.
            </p>
        </div>
        
        <p style="color: #4b5563; font-size: 15px; line-height: 1.6;">
            If you did not request this change, you can safely ignore this email. Your password will remain unchanged.
        </p>
        
        <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 25px 0;">
        
        <p style="color: #6b7280; font-size: 14px; line-height: 1.5; margin-bottom: 0;">
            Regards,<br>
            <strong style="color: #4F46E5; font-size: 16px;">Connecttoinspect Team</strong>
        </p>
    </div>
</body>
</html>