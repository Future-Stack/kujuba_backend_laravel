<!DOCTYPE html>
<html>
<head>
    <title>Password Changed Successfully</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <h2 style="color: #2563EB; margin-top: 0;">Password Changed Successfully</h2>
        <p style="color: #555; font-size: 16px; line-height: 1.5;">Hello {{ $user->first_name }},</p>
        <p style="color: #555; font-size: 16px; line-height: 1.5;">This is a confirmation email that the password for your Connecttoinspect account has been successfully updated.</p>
        
        <div style="background: #EFF6FF; border-left: 4px solid #2563EB; padding: 15px; margin: 25px 0; border-radius: 4px;">
            <p style="color: #1E40AF; margin: 0; font-size: 15px; font-weight: 500;">
                If you made this change, you can safely ignore this email. You can now log in using your new password.
            </p>
        </div>
        
        <p style="color: #EF4444; font-size: 15px; line-height: 1.5; font-weight: bold;">Important Security Notice:</p>
        <p style="color: #555; font-size: 15px; line-height: 1.5; margin-top: 0;">If you did NOT request this change, please contact our support team immediately to secure your account.</p>
        
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #777; font-size: 14px; line-height: 1.5;">Regards,<br><strong style="color: #2563EB;">Connecttoinspect</strong></p>
    </div>
</body>
</html>