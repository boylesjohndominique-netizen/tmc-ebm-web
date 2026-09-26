<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Authentication for the admin web system plus public student
 * self-registration for the mobile app.
 *
 * Login gating by account status:
 *   - Banned  -> rejected outright
 *   - Pending -> rejected until an admin verifies the account
 *   - Active  -> administrators may use the web system; other roles
 *                authenticate here to get an API token (mobile app)
 */
class AuthController extends Controller
{
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

        if (!$user || !Hash::check($data['password'], $user->password)) {
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
        if (!$user->isSuperAdmin() && $user->role?->name !== 'Administrator' && !$user->canAccess('dashboard', 'view')) {
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
     * Public self-registration (mobile app): creates a Pending Student
     * account that an administrator must verify before login is allowed.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $studentRole = Role::where('name', 'Student')->firstOrFail();

        $user = User::create([
            'role_id' => $studentRole->id,
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'contact_number' => $data['contact_number'] ?? null,
            'password' => $data['password'],
            'account_status' => 'Pending',
        ]);

        // Notify every administrator so someone verifies the new account.
        $admins = User::whereHas('role', fn ($q) => $q->where('name', 'Administrator'))->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'New Student Registration',
                'message' => "{$user->first_name} {$user->last_name} (@{$user->username}) "
                    .'registered as a student and is awaiting verification.',
                'type' => 'Request',
            ]);
        }

        return response()->json([
            'message' => 'Registration successful. Your account is pending '
                .'verification by an administrator.',
            'user' => $user->load('role'),
        ], 201);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink([
            'email' => $data['email'],
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent successfully.',
            ]);
        }

        return response()->json([
            'message' => 'Unable to send password reset link.',
        ], 422);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password reset successfully.',
            ]);
        }

        return response()->json([
            'message' => 'Invalid or expired password reset token.',
        ], 422);
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
}
