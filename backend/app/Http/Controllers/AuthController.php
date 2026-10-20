<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetCodeMail;
use App\Mail\VerificationCodeMail;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Authentication for the admin web system plus public student
 * self-registration for the mobile app.
 *
 * Login gating by account status:
 *   - Banned  -> rejected outright
 *   - Pending -> rejected until an admin verifies the account
 *   - Active  -> administrators may use the web system; other roles
 *                authenticate here to get an API token (mobile app)
 *
 * Mobile-specific endpoints:
 *   - POST /api/mobile/login       — allows students to log in
 *   - POST /api/verify-email       — verify the 6-digit email code
 *   - POST /api/resend-verification — resend the 6-digit code
 *   - POST /api/forgot-password    — send a 6-digit reset code
 *   - POST /api/reset-password     — verify code + set new password
 */
class AuthController extends Controller
{
    /**
     * Web admin login (original behaviour — students blocked).
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where(function ($query) use ($data) {
            $query->where('email', $data['login'])
                ->orWhere('username', $data['login']);
        })
            ->with('role')
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid username/email or password.',
            ], 422);
        }

        // Banned accounts can never sign in.
        if ($user->isBanned()) {
            return response()->json([
                'message' => 'This account has been banned. '
                    .'Please contact the college administrator.',
            ], 403);
        }

        // Students self-register as Pending and must be verified first.
        if ($user->isPending()) {
            return response()->json([
                'message' => 'Your account is still pending verification. '
                    .'Please wait for an administrator to verify your registration.',
            ], 403);
        }

        // Web system is for staff-side roles; students use the mobile app.
        if ($user->isStudent()) {
            return response()->json([
                'message' => 'This system is for administrators only. '
                    .'Please use the mobile app instead.',
            ], 403);
        }

        // Staff/Faculty without even dashboard access are blocked at login.
        if (! $user->isSuperAdmin() && $user->role?->name !== 'Administrator' && ! $user->canAccess('dashboard', 'view')) {
            return response()->json([
                'message' => 'Your account does not have access to the web system. '
                    .'Please contact the Super Admin.',
            ], 403);
        }

        $token = $user->createToken('tmc-frontend')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                ...$user->toArray(),
                'permission_map' => $user->permissionMap(),
                'is_super_admin' => $user->isSuperAdmin(),
            ],
        ]);
    }

    /**
     * Mobile app login — students ARE allowed; staff/admin can use it too.
     */
    public function mobileLogin(Request $request)
    {
        $data = $request->validate([
            // Accept `login` (web convention) or `username` (older mobile builds)
            'login' => ['required_without:username', 'string', 'nullable'],
            'username' => ['required_without:login', 'string', 'nullable'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($data['login'] ?? $data['username'] ?? '');

        $user = User::where(function ($query) use ($identifier) {
            $query->where('email', $identifier)
                ->orWhere('username', $identifier);
        })
            ->with('role')
            ->first();

        // Give the student a specific reason instead of one generic message.
        // Note: the rest of this API already distinguishes account states
        // (banned/pending/no-account on the verification endpoints), and
        // registration reveals which emails/usernames are taken, so this
        // does not introduce a new class of user enumeration. The web admin
        // login (/login) intentionally keeps its generic message.
        if (! $user) {
            return response()->json([
                'message' => 'Username/email not found. Please check your username or email.',
                'error' => 'account_not_found',
            ], 422);
        }

        if (! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Incorrect password. Please try again.',
                'error' => 'incorrect_password',
            ], 422);
        }

        if ($user->isBanned()) {
            return response()->json([
                'message' => 'This account has been banned. '
                    .'Please contact the college administrator.',
                'error' => 'account_banned',
                'banned' => true,
                'ban_reason' => $user->ban_reason,
            ], 403);
        }

        // Note: Pending students CAN log in and browse, but they cannot borrow
        // until an administrator verifies their uploaded ID photos.

        // Check if email is verified
        if (! $user->email_verified_at) {
            // Resend code if expired
            $this->generateAndSendVerificationCode($user);

            return response()->json([
                'message' => 'Please verify your email address first. A new verification code has been sent.',
                'requires_email_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        $token = $user->createToken('tmc-mobile')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                ...$user->toArray(),
                'role_name' => $user->role?->name,
                'account_status' => $user->account_status,
                // Verification is tracked by verified_at, independent of the
                // ban lifecycle — a banned-then-unbanned student stays exactly
                // as verified as they were before the ban.
                'is_id_verified' => $user->isIdVerified(),
            ],
        ]);
    }

    /**
     * Public self-registration (mobile app): creates a Pending Student
     * account that an administrator must verify before login is allowed.
     * Sends a 6-digit email verification code.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'id_number' => ['nullable', 'string', 'max:20', 'regex:/^\d{2}-\d{6}$/'],
            'username' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $studentRole = Role::where('name', 'Student')->firstOrFail();

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $baseUsername = trim($data['username'] ?? '') !== ''
            ? trim($data['username'])
            : trim($data['first_name']);
        $username = $baseUsername;
        $suffix = 1;
        while (User::where('username', $username)->exists()) {
            $username = "{$baseUsername}{$suffix}";
            $suffix++;
        }

        $user = User::create([
            'role_id' => $studentRole->id,
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'id_number' => $data['id_number'] ?? null,
            'username' => $username,
            'email' => $data['email'],
            'contact_number' => $data['contact_number'] ?? null,
            'password' => $data['password'],
            'account_status' => 'Pending',
            'email_verification_code' => $code,
            'email_code_expires_at' => now()->addMinutes(15),
        ]);

        // Send verification email with code and one-click verification link
        try {
            $verificationUrl = url('/verify-email-link?email='.urlencode($user->email).'&code='.$code);
            Mail::to($user->email)->send(new VerificationCodeMail(
                code: $code,
                userName: $user->first_name,
                verificationUrl: $verificationUrl,
            ));
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email: '.$e->getMessage());
        }

        // Notify every administrator so someone verifies the new account.
        $admins = User::whereHas('role', fn ($q) => $q->where('name', 'Administrator'))->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'New Student Registration',
                'message' => "{$user->first_name} {$user->last_name} (@{$user->username}) "
                    .'registered as a student and is awaiting verification.',
                'type' => 'Registration',
            ]);
        }

        return response()->json([
            'message' => 'Registration successful. A verification code has been sent to your email.',
            'requires_email_verification' => true,
            'email' => $user->email,
        ], 201);
    }

    /**
     * Verify email with the 6-digit code.
     */
    public function verifyEmail(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'No account found with this email.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email is already verified.']);
        }

        if ($user->email_verification_code !== $data['code']) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        if ($user->email_code_expires_at && $user->email_code_expires_at->isPast()) {
            return response()->json(['message' => 'Verification code has expired. Please request a new one.'], 422);
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_code_expires_at' => null,
        ]);

        // Auto-login: issue a token so the mobile app can navigate straight to
        // the dashboard without requiring a second manual login step.
        $token = $user->createToken('tmc-mobile')->plainTextToken;
        $user->load('role');

        return response()->json([
            'message' => 'Email verified successfully. Welcome!',
            'token' => $token,
            'user' => [
                ...$user->toArray(),
                'role_name' => $user->role?->name,
                'account_status' => $user->account_status,
                'is_id_verified' => $user->isIdVerified(),
            ],
        ]);
    }

    /**
     * Resend the 6-digit email verification code.
     */
    public function resendVerification(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'No account found with this email.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email is already verified.']);
        }

        $this->generateAndSendVerificationCode($user);

        return response()->json([
            'message' => 'A new verification code has been sent to your email.',
        ]);
    }

    /**
     * Send a 6-digit password reset code (mobile-friendly, no links).
     */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            // Don't reveal whether the email exists
            return response()->json([
                'message' => 'If an account exists with this email, a reset code has been sent.',
            ]);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'email_verification_code' => $code,
            'email_code_expires_at' => now()->addMinutes(15),
        ]);

        try {
            Mail::to($user->email)->send(new PasswordResetCodeMail(
                code: $code,
                userName: $user->first_name,
            ));
        } catch (\Exception $e) {
            \Log::error('Failed to send password reset email: '.$e->getMessage());
        }

        return response()->json([
            'message' => 'If an account exists with this email, a reset code has been sent.',
        ]);
    }

    /**
     * Reset password using the 6-digit code.
     */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Invalid email or code.'], 422);
        }

        if ($user->email_verification_code !== $data['code']) {
            return response()->json(['message' => 'Invalid reset code.'], 422);
        }

        if ($user->email_code_expires_at && $user->email_code_expires_at->isPast()) {
            return response()->json(['message' => 'Reset code has expired. Please request a new one.'], 422);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'email_verification_code' => null,
            'email_code_expires_at' => null,
        ])->save();

        // Revoke existing tokens for security
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully. You can now log in with your new password.',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['role', 'permissions']);

        return response()->json([
            ...$user->toArray(),
            'permission_map' => $user->permissionMap(),
            'is_super_admin' => $user->isSuperAdmin(),
        ]);
    }

    /**
     * Generate and send a new 6-digit verification code.
     */
    private function generateAndSendVerificationCode(User $user): void
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'email_verification_code' => $code,
            'email_code_expires_at' => now()->addMinutes(15),
        ]);

        try {
            $verificationUrl = url('/verify-email-link?email='.urlencode($user->email).'&code='.$code);
            Mail::to($user->email)->send(new VerificationCodeMail(
                code: $code,
                userName: $user->first_name,
                verificationUrl: $verificationUrl,
            ));
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email: '.$e->getMessage());
        }
    }
}
