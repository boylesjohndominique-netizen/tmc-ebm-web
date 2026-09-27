<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => response()->json([
    'system' => 'TMC Equipment Borrowing and Tracking Management System',
    'status' => 'API running',
]));

Route::get('/verify-email-link', function (Request $request) {
    $email = $request->query('email');
    $code = $request->query('code');

    if (!$email || !$code) {
        return response()->view('emails.verification-result', [
            'success' => false,
            'title' => 'Invalid Verification Link',
            'message' => 'The verification link is incomplete or malformed. Please try again or enter the code manually in the mobile app.',
        ], 400);
    }

    $user = User::where('email', $email)->first();

    if (!$user) {
        return response()->view('emails.verification-result', [
            'success' => false,
            'title' => 'User Not Found',
            'message' => 'No account associated with this email address was found.',
        ], 404);
    }

    if ($user->email_verified_at) {
        return response()->view('emails.verification-result', [
            'success' => true,
            'title' => 'Already Verified',
            'message' => 'Your email address is already verified! You can return to the mobile application and sign in.',
            'userName' => $user->first_name,
        ]);
    }

    if ($user->email_verification_code !== $code) {
        return response()->view('emails.verification-result', [
            'success' => false,
            'title' => 'Invalid Code',
            'message' => 'This verification code is invalid or has been superseded. Please request a new code in the app.',
        ], 422);
    }

    if ($user->email_code_expires_at && $user->email_code_expires_at->isPast()) {
        return response()->view('emails.verification-result', [
            'success' => false,
            'title' => 'Code Expired',
            'message' => 'This verification link has expired (15 minutes limit). Please request a new one from the mobile application.',
        ], 422);
    }

    $user->update([
        'email_verified_at' => now(),
        'email_verification_code' => null,
        'email_code_expires_at' => null,
    ]);

    return response()->view('emails.verification-result', [
        'success' => true,
        'title' => 'Email Verified Successfully!',
        'message' => 'Your email address has been verified. You can now open the TMC Equipment Borrowing mobile app to sign in.',
        'userName' => $user->first_name,
    ]);
});
