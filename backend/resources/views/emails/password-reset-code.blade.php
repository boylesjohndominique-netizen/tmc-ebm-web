<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin:0; padding:0; background:#f4f6fb; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; }
        .wrap { max-width:520px; margin:40px auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(18,42,92,.08); }
        .header { background:linear-gradient(135deg,#FF8C4C,#EF4455,#D83B3B); padding:32px 28px 24px; text-align:center; }
        .header h1 { color:#fff; font-size:22px; margin:0; font-weight:700; letter-spacing:-0.3px; }
        .header p { color:rgba(255,255,255,.85); font-size:13px; margin:8px 0 0; }
        .body { padding:32px 28px; }
        .greeting { font-size:16px; color:#101A2D; font-weight:600; margin:0 0 12px; }
        .text { font-size:14px; color:#5A6980; line-height:1.6; margin:0 0 24px; }
        .code-box { text-align:center; margin:24px 0 28px; }
        .code { display:inline-block; font-size:36px; font-weight:800; letter-spacing:8px; color:#EF4455; background:#FFF0F1; padding:16px 32px; border-radius:12px; border:2px dashed #EF445540; }
        .note { font-size:12px; color:#97A2B6; text-align:center; margin:0; line-height:1.5; }
        .footer { background:#f8f9fc; padding:20px 28px; text-align:center; border-top:1px solid #E7EBF3; }
        .footer p { font-size:11px; color:#97A2B6; margin:0; line-height:1.5; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="header">
            <h1>🔒 Password Reset</h1>
            <p>TMC Equipment Borrowing System</p>
        </div>
        <div class="body">
            <p class="greeting">Hi {{ $userName }},</p>
            <p class="text">We received a request to reset your password. Enter this code in the app to set a new password.</p>
            <div class="code-box">
                <span class="code">{{ $code }}</span>
            </div>
            <p class="note">This code expires in <strong>15 minutes</strong>.<br>If you didn't request a password reset, you can safely ignore this email.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Trinidad Municipal College &middot; Equipment Borrowing System</p>
        </div>
    </div>
</body>
</html>
