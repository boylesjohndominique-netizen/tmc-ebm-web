<?php

namespace App\Http\Controllers;

use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\Notification;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;

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
    // PROFILE
    // =========================================================================

    /**
     * Get the current user's profile.
     */
    public function profile(Request $request)
    {
        return response()->json($request->user()->load('role'));
    }

    /**
     * Update profile details.
     */
    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:30'],
        ]);

        $request->user()->update($data);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $request->user()->fresh()->load('role'),
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

        if (!\Hash::check($data['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $request->user()->forceFill([
            'password' => \Hash::make($data['password']),
        ])->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
