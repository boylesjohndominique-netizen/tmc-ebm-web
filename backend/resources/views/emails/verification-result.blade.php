<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — TMCEBMS</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #f0f4f9; font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; color: #1e293b; }
        .card { background: #ffffff; width: 100%; max-width: 480px; border-radius: 20px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); overflow: hidden; text-align: center; }
        .card-header { background: {{ $success ? 'linear-gradient(135deg, #10b981, #059669)' : 'linear-gradient(135deg, #ef4444, #dc2626)' }}; padding: 40px 24px; color: white; }
        .icon { width: 72px; height: 72px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 36px; margin-bottom: 16px; backdrop-filter: blur(4px); }
        .card-header h1 { margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px; }
        .card-body { padding: 36px 28px; }
        .user-greeting { font-size: 18px; font-weight: 600; color: #0f172a; margin-bottom: 8px; }
        .card-body p { font-size: 15px; color: #64748b; line-height: 1.6; margin: 0 0 24px; }
        .app-badge { display: inline-flex; align-items: center; gap: 8px; background: #f1f5f9; padding: 10px 20px; border-radius: 999px; font-size: 13px; font-weight: 600; color: #334155; }
        .footer { padding: 16px 28px; background: #fafafa; border-top: 1px solid #f1f5f9; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <div class="icon">{{ $success ? '✓' : '✗' }}</div>
            <h1>{{ $title }}</h1>
        </div>
        <div class="card-body">
            @if(!empty($userName))
                <div class="user-greeting">Hi {{ $userName }},</div>
            @endif
            <p>{{ $message }}</p>
            <div class="app-badge">
                <span>📱 Return to the TMCEBMS mobile app to continue</span>
            </div>
        </div>
        <div class="footer">
            Trinidad Municipal College &middot; Equipment Borrowing System
        </div>
    </div>
</body>
</html>
