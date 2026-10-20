<?php

namespace App\Http\Controllers;

use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\Notification;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Mobile app API endpoints for authenticated student users.
 * Provides equipment browsing, borrowing, returns, notifications, and profile.
 */
class MobileController extends Controller
{
    // =========================================================================
    // EQUIPMENT
    // =========================================================================

    /**
     * List all equipment (no permission check — any authenticated user).
     */
    public function equipment()
    {
        return response()->json(
            Equipment::with('category')
                ->where('status', 'Available')
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * Get single equipment details.
     */
    public function equipmentShow(Equipment $equipment)
    {
        return response()->json($equipment->load('category'));
    }

    // =========================================================================
    // BORROWING
    // =========================================================================

    /**
     * Submit a new borrowing request.
     *
     * The mobile app sends a single-item form; the payload is normalised
     * into one borrowing request with one borrowing item. The student's
     * purpose (and optional notes) is stored in `remarks`, which is the
     * column the admin Borrowing/Return pages read and display.
     */
    public function submitBorrowRequest(Request $request)
    {
        $data = $request->validate([
            'equipment_id' => ['required', 'exists:equipment,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'borrowing_date' => ['required', 'date', 'after_or_equal:today'],
            'expected_return_date' => ['required', 'date', 'after:borrowing_date'],
            'purpose' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $equipment = Equipment::findOrFail($data['equipment_id']);

        // Guard: only admin-verified (Active) accounts may borrow.
        if ($request->user()->isPending()) {
            return response()->json([
                'message' => 'Your account has not been verified by an administrator yet. '
                    .'Please submit your ID photos in the Profile screen and wait for approval.',
            ], 403);
        }

        if ($equipment->available_quantity < (int) $data['quantity']) {
            return response()->json([
                'message' => "Requested quantity exceeds availability for {$equipment->name}.",
            ], 422);
        }

        $purpose = $data['purpose'];
        $notes = $data['notes'] ?? null;

        $remarks = $notes
            ? $purpose.' — Notes: '.$notes
            : $purpose;

        $borrowing = BorrowingRequest::create([
            'user_id' => $request->user()->id,
            'borrowing_date' => $data['borrowing_date'],
            'expected_return_date' => $data['expected_return_date'],
            'status' => 'Pending',
            'remarks' => $remarks,
        ]);

        $borrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => (int) $data['quantity'],
        ]);

        // Tell the staff exactly what happened — never a generic "verify"
        // message for a non-verification event.
        $name = trim($request->user()->first_name.' '.$request->user()->last_name);
        $admins = User::whereHas('role', fn ($q) => $q->where('name', 'Administrator'))->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'New Borrow Request',
                'message' => "{$name} (@{$request->user()->username}) requested "
                    ."{$equipment->name} ×{$data['quantity']} — waiting for your review.",
                'type' => 'Borrow',
            ]);
        }

        return response()->json([
            'message' => 'Borrowing request submitted successfully.',
            'data' => $borrowing->load('items.equipment'),
        ], 201);
    }

    /**
     * List the current user's borrowing requests, including any return
     * requests so the app can show return status without extra calls.
     */
    public function myBorrowings(Request $request)
    {
        $borrowings = BorrowingRequest::where('user_id', $request->user()->id)
            ->with(['items.equipment.category', 'returns'])
            ->latest()
            ->get();

        return response()->json($borrowings);
    }

    /**
     * Cancel a pending borrowing request.
     */
    public function cancelBorrowing(Request $request, BorrowingRequest $borrowing)
    {
        if ($borrowing->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($borrowing->status !== 'Pending') {
            return response()->json(['message' => 'Only pending requests can be cancelled.'], 422);
        }

        $borrowing->update(['status' => 'Cancelled']);

        return response()->json([
            'message' => 'Borrowing request cancelled.',
            'data' => $borrowing->fresh()->load('items.equipment'),
        ]);
    }

    // =========================================================================
    // RETURNS
    // =========================================================================

    /**
     * Submit a return request for an approved borrowing.
     */
    public function submitReturn(Request $request, BorrowingRequest $borrowing)
    {
        if ($borrowing->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($borrowing->status !== 'Approved') {
            return response()->json(['message' => 'Only approved borrowings can be returned.'], 422);
        }

        $existing = ReturnRequest::where('borrowing_request_id', $borrowing->id)
            ->where('status', 'Pending')
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'A return request is already pending for this borrowing.'], 422);
        }

        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $returnReq = ReturnRequest::create([
            'borrowing_request_id' => $borrowing->id,
            'user_id' => $request->user()->id,
            'remarks' => $data['remarks'] ?? null,
            'status' => 'Pending',
        ]);

        // Distinct return-request notification (type: Return) so the admin
        // inbox can distinguish it from borrow/verification events.
        $name = trim($request->user()->first_name.' '.$request->user()->last_name);
        $admins = User::whereHas('role', fn ($q) => $q->where('name', 'Administrator'))->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'New Return Request',
                'message' => "{$name} (@{$request->user()->username}) submitted a return for request "
                    ."#{$borrowing->id} — waiting for your review.",
                'type' => 'Return',
            ]);
        }

        return response()->json([
            'message' => 'Return request submitted successfully.',
            'data' => $returnReq,
        ], 201);
    }

    // =========================================================================
    // NOTIFICATIONS
    // =========================================================================

    /**
     * List the current user's notifications.
     */
    public function notifications(Request $request)
    {
        return response()->json(
            Notification::where('user_id', $request->user()->id)
                ->latest()
                ->get()
                ->map(fn (Notification $n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'message' => $n->message,
                    'type' => $n->type,
                    'read_at' => $n->read_at?->toISOString(),
                    'created_at' => $n->created_at?->toISOString(),
                ])
        );
    }

    /**
     * Unread notification count.
     */
    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => Notification::where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markRead(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    // =========================================================================
    // ID VERIFICATION
    // =========================================================================

    /**
     * Submit front/back ID photos for admin verification.
     *
     * Photos are stored on the PRIVATE disk (storage/app/private) — never the
     * public one — so they are only reachable through authorized endpoints
     * that stream them to the account owner and to administrators. Submission
     * does not change `verified_at`; only an admin's approval does.
     */
    public function submitIdVerification(Request $request)
    {
        $data = $request->validate([
            'id_front' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'id_back' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $user = $request->user();

        $frontPath = $data['id_front']->store('id-verifications/'.$user->id, 'local');
        $backPath = $data['id_back']->store('id-verifications/'.$user->id, 'local');

        // Delete the previous submissions so stale ID photos don't accumulate.
        if ($user->id_front_path && Storage::disk('local')->exists($user->id_front_path)) {
            Storage::disk('local')->delete($user->id_front_path);
        }
        if ($user->id_back_path && Storage::disk('local')->exists($user->id_back_path)) {
            Storage::disk('local')->delete($user->id_back_path);
        }

        $user->update([
            'id_front_path' => $frontPath,
            'id_back_path' => $backPath,
            'id_submitted_at' => now(),
        ]);

        // Queue a review notification for every administrator.
        $name = trim($user->first_name.' '.$user->last_name);
        $admins = User::whereHas('role', fn ($q) => $q->where('name', 'Administrator'))->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'Verification Documents Submitted',
                'message' => "{$name} (@{$user->username}) has submitted ID photos for verification and is waiting for your review.",
                'type' => 'Verification',
            ]);
        }

        return response()->json([
            'message' => 'ID photos submitted. An administrator will review your verification.',
            'id_submitted_at' => $user->id_submitted_at?->toISOString(),
        ], 201);
    }

    /**
     * Stream the signed-in user's own submitted ID image (front|back).
     * Kept alongside the admin endpoint so users can re-check their photos.
     */
    public function myIdPhoto(Request $request, string $side)
    {
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $path = $side === 'front' ? $request->user()->id_front_path : $request->user()->id_back_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'No ID photo submitted.'], 404);
        }

        return response()->file(Storage::disk('local')->path($path));
    }

    // =========================================================================
    // PROFILE
    // =========================================================================

    /**
     * Get the current user's profile.
     */
    public function profile(Request $request)
    {
        $user = $request->user()->load('role');

        return response()->json(array_merge($user->toArray(), [
            // Derived flags so the app never infers verification from the
            // ban lifecycle. Photo paths are intentionally excluded — the
            // app fetches images via the streaming endpoint if needed.
            'is_id_verified' => $user->isIdVerified(),
        ]));
    }

    /**
     * Update profile details (and optionally student ID / profile photo).
     */
    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'id_number' => ['nullable', 'string', 'max:20', 'regex:/^\d{2}-\d{6}$/'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        unset($data['avatar'], $data['remove_avatar']);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            if ($user->profile_photo_path && Storage::disk('local')->exists($user->profile_photo_path)) {
                Storage::disk('local')->delete($user->profile_photo_path);
            }
            $data['profile_photo_path'] = $request->file('avatar')->store('user-avatars', 'local');
        } elseif ($request->boolean('remove_avatar')) {
            if ($user->profile_photo_path && Storage::disk('local')->exists($user->profile_photo_path)) {
                Storage::disk('local')->delete($user->profile_photo_path);
            }
            $data['profile_photo_path'] = null;
        }

        $user->update($data);
        $fresh = $user->fresh()->load('role');

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => array_merge($fresh->toArray(), [
                'is_id_verified' => $fresh->isIdVerified(),
            ]),
        ]);
    }

    /**
     * Upload or replace the signed-in user's profile avatar directly.
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $user = $request->user();
        if ($user->profile_photo_path && Storage::disk('local')->exists($user->profile_photo_path)) {
            Storage::disk('local')->delete($user->profile_photo_path);
        }

        $path = $request->file('avatar')->store('user-avatars', 'local');
        $user->update(['profile_photo_path' => $path]);
        $fresh = $user->fresh()->load('role');

        return response()->json([
            'message' => 'Profile picture updated.',
            'user' => array_merge($fresh->toArray(), [
                'is_id_verified' => $fresh->isIdVerified(),
            ]),
        ]);
    }

    /**
     * Stream a user profile avatar image.
     */
    public function userAvatar(string $filename)
    {
        $safe = basename($filename);
        $path = 'user-avatars/'.$safe;

        if (! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Avatar not found.'], 404);
        }

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! \Hash::check($data['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $request->user()->forceFill([
            'password' => \Hash::make($data['password']),
        ])->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    /**
     * Read-only system settings & platform policies authored by administrators.
     */
    public function settings()
    {
        $raw = Setting::get(Setting::PLATFORM_POLICIES);
        $decoded = $raw ? json_decode($raw, true) : [];

        return response()->json([
            'overdue_fee_per_day' => (float) Setting::get(Setting::OVERDUE_FEE_PER_DAY, 0),
            'platform_policies' => is_array($decoded) ? array_values($decoded) : [],
        ]);
    }
}
