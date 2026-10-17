<?php
namespace App\Http\Controllers\Admin;

use App\Events\UserBanned;
use App\Http\Controllers\Controller;
use App\Models\BorrowingRequest;
use App\Models\Notification;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Super-admin user management.
 *
 * Only Admin (Administrator), Faculty and Staff accounts can be created
 * or edited here — Student accounts come from public self-registration
 * and are managed through verify / ban / unban instead.
 */
class UserManagementController extends Controller
{
    /** Roles the super admin may assign to accounts it creates. */
    public const CREATABLE_ROLES = ['Administrator', 'Faculty', 'Staff'];

    public function index()
    {
        return response()->json(
            User::with('role')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->makeHidden(['password', 'remember_token'])
        );
    }

    public function show(User $user)
    {
        $borrowed = BorrowingRequest::where('user_id', $user->id)->where('status', 'Approved')->with('items.equipment')->get();

        return response()->json([
            'user' => array_merge($user->load('role')->toArray(), [
                // Verification marker, independent of account/ban status so
                // the admin UI can show the two dimensions separately.
                'is_id_verified' => $user->isIdVerified(),
            ]),
            'statistics' => [
                'total_requests' => BorrowingRequest::where('user_id', $user->id)->count(),
                'pending_requests' => BorrowingRequest::where('user_id', $user->id)->where('status', 'Pending')->count(),
                'active_borrowings' => $borrowed->count(),
                'currently_borrowed_units' => (int) $borrowed->sum(fn ($b) => $b->items->sum('quantity')),
                'completed' => BorrowingRequest::where('user_id', $user->id)->where('status', 'Completed')->count(),
                'overdue' => BorrowingRequest::where('user_id', $user->id)->where('status', 'Approved')
                    ->whereDate('expected_return_date', '<', now()->toDateString())->count(),
                'returns' => ReturnRequest::where('user_id', $user->id)->count(),
            ],
            'borrowings' => BorrowingRequest::with('items.equipment')->where('user_id', $user->id)->latest()->limit(10)->get(),
            'returns' => ReturnRequest::with('borrowing')->where('user_id', $user->id)->latest()->limit(10)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'role' => ['required', Rule::in(self::CREATABLE_ROLES)],
            'username' => 'required|string|max:100|unique:users,username',
            'email' => 'required|email|max:150|unique:users,email',
            'contact_number' => 'nullable|string|max:30',
            'password' => 'required|string|min:8',
        ]);

        $role = Role::where('name', $data['role'])->firstOrFail();
        $data['role_id'] = $role->id;
        $data['account_status'] = 'Active'; // created by the super admin, no verification step
        unset($data['role']);

        $user = User::create($data);

        return response()->json($user->load('role'), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            // Students are managed via verify/ban, not edited into staff roles.
            'role' => ['required', Rule::in(self::CREATABLE_ROLES)],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'contact_number' => 'nullable|string|max:30',
            'password' => 'nullable|string|min:8',
        ]);

        if ($user->isStudent()) {
            return response()->json([
                'message' => 'Student accounts cannot be edited here. Verify, ban, or delete them instead.',
            ], 422);
        }

        $role = Role::where('name', $data['role'])->firstOrFail();

        $user->update([
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'contact_number' => $data['contact_number'] ?? null,
            'role_id' => $role->id,
            ...(!empty($data['password']) ? ['password' => $data['password']] : []),
        ]);

        return response()->json($user->fresh()->load('role'));
    }

    /**
     * Approve a Pending (student self-registration) account.
     */
    public function verify(Request $request, User $user)
    {
        if (!$user->isPending()) {
            return response()->json(['message' => 'Only pending accounts can be verified.'], 422);
        }

        // Verification only ever happens here — ban/unban must not touch it.
        $user->update([
            'account_status' => 'Active',
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Account Verified',
            'message' => 'Your account has been verified. You can now log in to the mobile app.',
            'type' => 'Success',
        ]);

        return response()->json([
            'message' => 'Account verified successfully.',
            'user' => $user->fresh()->load('role'),
        ]);
    }

    /**
     * Ban an account: it can no longer log in anywhere.
     */
    public function ban(Request $request, User $user)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot ban your own account.'], 422);
        }

        if ($user->isAdmin()) {
            return response()->json(['message' => 'Administrator accounts cannot be banned.'], 422);
        }

        if ($user->isBanned()) {
            return response()->json(['message' => 'This account is already banned.'], 422);
        }

        $user->update([
            'account_status' => 'Banned',
            'banned_at' => now(),
            'ban_reason' => $data['reason'],
        ]);

        // Revoke access immediately.
        $user->tokens()->delete();

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Account Banned',
            'message' => 'Your account has been banned. Reason: '.$data['reason'],
            'type' => 'Account',
        ]);

        // Push the ban to the user's private channel. A mobile app that is
        // still open shows the ban notice + appeal form and signs out — the
        // socket survives even though the API token was just revoked.
        DB::afterCommit(function () use ($user, $data) {
            SafeBroadcast::send(new UserBanned($user->id, $data['reason']));
        });

        return response()->json([
            'message' => 'Account banned successfully.',
            'user' => $user->fresh()->load('role'),
        ]);
    }

    /**
     * Lift a ban and restore the account's pre-ban status.
     *
     * A banned account keeps whatever verification it had before the ban:
     * a previously verified account becomes Active again, while an account
     * that was never verified returns to Pending (it must still be verified
     * before it can borrow). Unbanning NEVER marks an account verified.
     */
    public function unban(Request $request, User $user)
    {
        if (!$user->isBanned()) {
            return response()->json(['message' => 'This account is not banned.'], 422);
        }

        // `verified_at` is untouched, so verification survives the ban cycle.
        $user->update([
            'account_status' => $user->isIdVerified() ? 'Active' : 'Pending',
            'banned_at' => null,
            'ban_reason' => null,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Ban Lifted',
            'message' => 'Your account has been reinstated. You can log in again.',
            'type' => 'Account',
        ]);

        return response()->json([
            'message' => 'Account unbanned successfully.',
            'user' => $user->fresh()->load('role'),
        ]);
    }

    /**
     * Stream a submitted ID photo (front|back) from the private disk.
     *
     * Images are never publicly reachable — this authorized endpoint is the
     * only way to view them, and it 404s gracefully when nothing was
     * submitted or the file is missing.
     */
    public function idPhoto(Request $request, User $user, string $side)
    {
        if (!in_array($side, ['front', 'back'], true)) {
            return response()->json(['message' => 'Unknown ID side.'], 404);
        }

        $path = $side === 'front' ? $user->id_front_path : $user->id_back_path;

        if (!$path || !\Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'No ID photo submitted.'], 404);
        }

        return response()->file(\Storage::disk('local')->path($path));
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }
        if ($user->isAdmin()) {
            return response()->json(['message' => 'Administrator accounts cannot be deleted.'], 422);
        }

        $hasHistory = BorrowingRequest::where('user_id', $user->id)->exists()
            || ReturnRequest::where('user_id', $user->id)->exists();

        // DB foreign keys cascade on delete; by default protect the audit trail.
        if ($hasHistory && !$request->boolean('force')) {
            return response()->json([
                'message' => 'This user has borrowing/return history. Delete their records too by confirming permanent deletion, or keep the user.',
                'requires_force' => true,
            ], 422);
        }

        $user->tokens()->delete();
        $user->delete(); // cascades to their borrowing requests, returns, notifications

        return response()->json(['message' => 'User deleted.']);
    }
}
