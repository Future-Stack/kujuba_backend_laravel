<!DOCTYPE html>
<html>
<head>
    <title>Welcome to Kujuba - Verify Email</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <h2 style="color: #333; margin-top: 0;">Verify Your Account</h2>
        <p style="color: #555; font-size: 16px; line-height: 1.5;">Hello {{ $user->first_name }},</p>
        <p style="color: #555; font-size: 16px; line-height: 1.5;">Thank you for registering with Connecttoinspect! To complete your registration and verify your email address, please use the OTP code provided below. This code is valid for 5 minutes.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #10B981; background: #E6F4EA; padding: 12px 25px; border-radius: 6px; display: inline-block;">
                {{ $otp }}
            </span>
        </div>
        
        <p style="color: #555; font-size: 16px; line-height: 1.5;">If you did not create an account, no further action is required.</p>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #777; font-size: 14px; line-height: 1.5;">Regards,<br><strong style="color: #10B981;">Connecttoinspect</strong></p>
    </div>
</body>
</html>