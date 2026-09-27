<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BorrowingStatusMail;
use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class BorrowingManagementController extends Controller
{
    public function index() { return response()->json(BorrowingRequest::with(['user','items.equipment'])->latest()->get()); }

    /**
     * Approve or reject a Pending borrowing request.
     *
     * Approving reserves stock (available_quantity decreases) once the
     * request is marked Approved; rejecting leaves stock untouched.
     * The borrower is notified either way, and the audit fields
     * (status, remarks, processed_by) feed the Transaction History page.
     */
    public function process(Request $request, BorrowingRequest $borrowing)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['Approved', 'Rejected'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        if ($borrowing->status !== 'Pending') {
            return response()->json([
                'message' => "Request #{$borrowing->id} has already been processed ({$borrowing->status}).",
            ], 409);
        }

        $updated = DB::transaction(function () use ($borrowing, $data) {
            // Lock the items + equipment rows so concurrent approvals can't
            // both decrement the same stock.
            $items = $borrowing->items()->with('equipment')->lockForUpdate()->get();

            if ($data['status'] === 'Approved') {
                foreach ($items as $item) {
                    $equipment = Equipment::lockForUpdate()->find($item->equipment_id);

                    if (!$equipment || $equipment->available_quantity < $item->quantity) {
                        abort(409, "Insufficient stock for {$item->equipment?->name}. The request cannot be approved.");
                    }
                }

                foreach ($items as $item) {
                    Equipment::whereKey($item->equipment_id)->decrement('available_quantity', $item->quantity);
                }
            }

            $borrowing->update([
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? $borrowing->remarks,
                'processed_by' => auth()->id(),
            ]);

            return $borrowing;
        });

        $name = trim($updated->user?->first_name.' '.$updated->user?->last_name) ?: 'your request';
        $remarks = $data['remarks'] ?? null;
        [$title, $message] = $data['status'] === 'Approved'
            ? ['Borrowing Request Approved', "Good news, {$name}! Your borrowing request #{$updated->id} was approved.".($remarks ? ' Note: '.$remarks : '')]
            : ['Borrowing Request Rejected', "Your borrowing request #{$updated->id} was rejected.".($remarks ? ' Reason: '.$remarks : '')];

        Notification::create([
            'user_id' => $updated->user_id,
            'title' => $title,
            'message' => $message,
            'type' => $data['status'] === 'Approved' ? 'Success' : 'Request',
        ]);

        // Send email notification
        if ($updated->user?->email) {
            $equipmentList = $updated->items->map(fn ($i) => $i->equipment?->name.' (×'.$i->quantity.')')->implode(', ');
            try {
                Mail::to($updated->user->email)->send(new BorrowingStatusMail(
                    userName: trim($updated->user->first_name),
                    status: $data['status'],
                    requestId: $updated->id,
                    equipmentList: $equipmentList,
                    remarks: $remarks,
                ));
            } catch (\Exception $e) {
                \Log::error('Failed to send borrowing status email: '.$e->getMessage());
            }
        }

        return response()->json([
            'message' => $data['status'] === 'Approved'
                ? "Request #{$updated->id} approved."
                : "Request #{$updated->id} rejected.",
            'data' => $updated->load(['user','items.equipment','processor']),
        ]);
    }
}
