<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <style>
        body { margin:0; padding:0; background:#f4f6fb; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; }
        .wrap { max-width:520px; margin:40px auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(18,42,92,.08); }
        .header { background:linear-gradient(135deg,#4C86FF,#2F6BFF,#1D4FD8); padding:32px 28px 24px; text-align:center; }
        .header h1 { color:#fff; font-size:22px; margin:0; font-weight:700; letter-spacing:-0.3px; }
        .header p { color:rgba(255,255,255,.85); font-size:13px; margin:8px 0 0; }
        .body { padding:32px 28px; }
        .greeting { font-size:16px; color:#101A2D; font-weight:600; margin:0 0 12px; }
        .text { font-size:14px; color:#5A6980; line-height:1.6; margin:0 0 20px; }
        .code-box { text-align:center; margin:20px 0 24px; }
        .code { display:inline-block; font-size:36px; font-weight:800; letter-spacing:8px; color:#2F6BFF; background:#E9EFFF; padding:16px 32px; border-radius:12px; border:2px dashed #2F6BFF40; }
        .divider { display:flex; align-items:center; text-align:center; margin:24px 0; color:#97A2B6; font-size:12px; text-transform:uppercase; letter-spacing:1px; }
        .divider::before, .divider::after { content:''; flex:1; border-bottom:1px solid #E7EBF3; }
        .divider:not(:empty)::before { margin-right:.5em; }
        .divider:not(:empty)::after { margin-left:.5em; }
        .btn-box { text-align:center; margin:20px 0; }
        .btn { display:inline-block; background:#2F6BFF; color:#ffffff !important; text-decoration:none; padding:14px 28px; border-radius:10px; font-weight:600; font-size:15px; box-shadow:0 4px 12px rgba(47,107,255,.3); }
        .note { font-size:12px; color:#97A2B6; text-align:center; margin:20px 0 0; line-height:1.5; }
        .footer { background:#f8f9fc; padding:20px 28px; text-align:center; border-top:1px solid #E7EBF3; }
        .footer p { font-size:11px; color:#97A2B6; margin:0; line-height:1.5; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="header">
            <h1>📧 Email Verification</h1>
            <p>TMC Equipment Borrowing System</p>
        </div>
        <div class="body">
            <p class="greeting">Hi {{ $userName }},</p>
            <p class="text">Welcome to TMCEBMS! Enter the 6-digit verification code below in the mobile app, or click the direct verification button to verify instantly:</p>
            
            <div class="code-box">
                <span class="code">{{ $code }}</span>
            </div>

            @if(!empty($verificationUrl))
            <div class="divider">or verify with one click</div>
            
            <div class="btn-box">
                <a href="{{ $verificationUrl }}" target="_blank" class="btn">Verify My Email Address</a>
            </div>
            @endif

            <p class="note">This code and link expire in <strong>15 minutes</strong>.<br>If you did not register for an account, please disregard this email.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Trinidad Municipal College &middot; Equipment Borrowing System</p>
        </div>
    </div>
</body>
</html>
