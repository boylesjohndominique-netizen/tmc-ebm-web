<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\Notification;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReturnManagementController extends Controller
{
    public function index() { return response()->json(ReturnRequest::with(['user','borrowing.items.equipment'])->latest()->get()); }

    /**
     * Confirm or reject a Pending return request.
     *
     * Confirming restocks the equipment (available_quantity increases) and
     * completes the parent borrowing request; rejecting leaves everything
     * untouched. The borrower is notified either way.
     */
    public function process(Request $request, ReturnRequest $returnRequest)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['Confirmed', 'Rejected'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        if ($returnRequest->status !== 'Pending') {
            return response()->json([
                'message' => "Return request #{$returnRequest->id} has already been processed ({$returnRequest->status}).",
            ], 409);
        }

        $updated = DB::transaction(function () use ($returnRequest, $data) {
            $borrowing = $returnRequest->borrowing()->with('items.equipment')->lockForUpdate()->firstOrFail();
            $items = $borrowing->items;

            if ($data['status'] === 'Confirmed') {
                foreach ($items as $item) {
                    Equipment::whereKey($item->equipment_id)->increment('available_quantity', $item->quantity);
                }

                $borrowing->update(['status' => 'Completed']);
            }

            $returnRequest->update([
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? $returnRequest->remarks,
                'processed_by' => auth()->id(),
            ]);

            return $returnRequest;
        });

        $name = trim($updated->user?->first_name.' '.$updated->user?->last_name) ?: 'your return';
        [$title, $message] = $data['status'] === 'Confirmed'
            ? ['Return Confirmed', "Thanks, {$name}! Your return for request #{$updated->borrowing_request_id} has been confirmed."]
            : ['Return Rejected', "Your return for request #{$updated->borrowing_request_id} was rejected.".($data['remarks'] ? ' Reason: '.$data['remarks'] : '')];

        Notification::create([
            'user_id' => $updated->user_id,
            'title' => $title,
            'message' => $message,
            'type' => $data['status'] === 'Confirmed' ? 'Success' : 'Request',
        ]);

        return response()->json([
            'message' => $data['status'] === 'Confirmed'
                ? "Return request #{$updated->id} confirmed."
                : "Return request #{$updated->id} rejected.",
            'data' => $updated->load(['user','borrowing.items.equipment','processor']),
        ]);
    }
}
