<!DOCTYPE html>
<html>
<head>
    <title>Password Reset OTP</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px;">
        <h2 style="color: #333;">Password Reset Request</h2>
        <p>Hello,</p>
        <p>We received a request to reset your password. Use the verification code below to complete the process. This OTP is valid for 5 minutes.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #4F46E5; background: #EEF2F6; padding: 10px 20px; border-radius: 5px;">
                {{ $otp }}
            </span>
        </div>
        
        <p>If you did not request this change, you can safely ignore this email.</p>
        <p>Regards,<br>Connecttoinspect</p>
    </div>
</body>
</html>