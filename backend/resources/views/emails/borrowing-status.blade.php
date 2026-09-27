<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin:0; padding:0; background:#f4f6fb; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; }
        .wrap { max-width:520px; margin:40px auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(18,42,92,.08); }
        .header { background:linear-gradient(135deg,#4C86FF,#2F6BFF,#1D4FD8); padding:32px 28px 24px; text-align:center; }
        .header.approved { background:linear-gradient(135deg,#34D399,#1FA760,#167D4A); }
        .header.rejected { background:linear-gradient(135deg,#FF8C4C,#EF4455,#D83B3B); }
        .header h1 { color:#fff; font-size:22px; margin:0; font-weight:700; letter-spacing:-0.3px; }
        .header p { color:rgba(255,255,255,.85); font-size:13px; margin:8px 0 0; }
        .body { padding:32px 28px; }
        .greeting { font-size:16px; color:#101A2D; font-weight:600; margin:0 0 12px; }
        .text { font-size:14px; color:#5A6980; line-height:1.6; margin:0 0 16px; }
        .status-badge { display:inline-block; padding:8px 20px; border-radius:8px; font-size:15px; font-weight:700; letter-spacing:0.3px; margin:8px 0 20px; }
        .status-badge.approved { background:#E6FAF0; color:#1FA760; }
        .status-badge.rejected { background:#FFF0F1; color:#EF4455; }
        .detail { background:#f8f9fc; border-radius:10px; padding:16px; margin:16px 0; }
        .detail-row { display:flex; justify-content:space-between; padding:6px 0; font-size:13px; }
        .detail-label { color:#97A2B6; }
        .detail-value { color:#101A2D; font-weight:600; }
        .remarks { background:#FFF8E1; border-left:4px solid #F59E0B; padding:12px 16px; border-radius:0 8px 8px 0; margin:16px 0; font-size:13px; color:#5A6980; line-height:1.5; }
        .footer { background:#f8f9fc; padding:20px 28px; text-align:center; border-top:1px solid #E7EBF3; }
        .footer p { font-size:11px; color:#97A2B6; margin:0; line-height:1.5; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="header {{ strtolower($status) }}">
            <h1>{{ $status === 'Approved' ? '✅' : '❌' }} Borrowing Request {{ $status }}</h1>
            <p>TMC Equipment Borrowing System</p>
        </div>
        <div class="body">
            <p class="greeting">Hi {{ $userName }},</p>
            @if($status === 'Approved')
                <p class="text">Great news! Your borrowing request has been approved. You may now proceed to pick up the equipment.</p>
            @else
                <p class="text">Unfortunately, your borrowing request has been rejected. Please review the details below.</p>
            @endif

            <span class="status-badge {{ strtolower($status) }}">{{ strtoupper($status) }}</span>

            <div class="detail">
                <div class="detail-row">
                    <span class="detail-label">Request ID</span>
                    <span class="detail-value">#{{ $requestId }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Equipment</span>
                    <span class="detail-value">{{ $equipmentList }}</span>
                </div>
            </div>

            @if($remarks)
                <div class="remarks">
                    <strong>Remarks:</strong> {{ $remarks }}
                </div>
            @endif

            <p class="text" style="margin-top:20px; font-size:13px;">Open the TMCEBMS mobile app for full details.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Trinidad Municipal College &middot; Equipment Borrowing System</p>
        </div>
    </div>
</body>
</html>
