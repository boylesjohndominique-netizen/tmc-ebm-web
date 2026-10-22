<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessBorrowingRequest;
use App\Mail\BorrowingStatusMail;
use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BorrowingManagementController extends Controller
{
    public function index()
    {
        return response()->json(BorrowingRequest::with(['user', 'items.equipment'])->latest()->get());
    }

    /**
     * Approve or reject a Pending borrowing request.
     *
     * Approving reserves stock (available_quantity decreases) once the
     * request is marked Approved; rejecting leaves stock untouched.
     * The borrower is notified either way, and the audit fields
     * (status, remarks, processed_by) feed the Transaction History page.
     */
    public function process(ProcessBorrowingRequest $request, BorrowingRequest $borrowing)
    {
        $data = $request->validated();

        $updated = DB::transaction(function () use ($borrowing, $data) {
            $borrowing = BorrowingRequest::lockForUpdate()->findOrFail($borrowing->id);
            abort_unless($borrowing->status === 'Pending', 409, 'This borrowing request has already been processed.');
            // Lock the items + equipment rows so concurrent approvals can't
            // both decrement the same stock.
            $items = $borrowing->items()->with('equipment.category')->orderBy('equipment_id')->lockForUpdate()->get();

            $receiptAttributes = [];

            if ($data['status'] === 'Approved') {
                $start = Carbon::parse($data['pickup_starts_at'])->utc();
                $end = Carbon::parse($data['pickup_ends_at'])->utc();
                abort_if($start->copy()->timezone('Asia/Manila')->toDateString() < $borrowing->borrowing_date->toDateString() ||
                    $end->copy()->timezone('Asia/Manila')->toDateString() > $borrowing->expected_return_date->toDateString(),
                    422, 'The pickup window must fall between the borrowing and expected return dates (Philippine time).');

                foreach ($items as $item) {
                    $equipment = Equipment::lockForUpdate()->find($item->equipment_id);

                    if (! $equipment || $equipment->available_quantity < $item->quantity) {
                        abort(409, "Insufficient stock for {$item->equipment?->name}. The request cannot be approved.");
                    }
                    $item->setRelation('equipment', $equipment->load('category'));
                }

                foreach ($items as $item) {
                    Equipment::whereKey($item->equipment_id)->decrement('available_quantity', $item->quantity);
                }

                $borrower = $borrowing->user;
                $receiptAttributes = [
                    'approved_at' => now(),
                    'pickup_starts_at' => $start,
                    'pickup_ends_at' => $end,
                    'pickup_status' => 'Awaiting',
                    'receipt_snapshot' => [
                        'request_id' => $borrowing->id,
                        'requested_at' => $borrowing->created_at->toISOString(),
                        'borrowing_date' => $borrowing->borrowing_date->toDateString(),
                        'expected_return_date' => $borrowing->expected_return_date->toDateString(),
                        'borrower' => [
                            'name' => trim(implode(' ', array_filter([$borrower->first_name, $borrower->middle_name, $borrower->last_name]))),
                            'id_number' => $borrower->id_number,
                            'email' => $borrower->email,
                            'contact_number' => $borrower->contact_number,
                        ],
                        'approved_by' => trim(auth()->user()->first_name.' '.auth()->user()->last_name),
                        'borrower_remarks' => $borrowing->remarks,
                        'approval_remarks' => $data['remarks'] ?? null,
                        'items' => $items->map(fn ($item) => [
                            'equipment_id' => $item->equipment_id,
                            'name' => $item->equipment?->name,
                            'description' => $item->equipment?->description,
                            'category' => $item->equipment?->category?->name,
                            'condition' => $item->equipment?->condition,
                            'quantity' => $item->quantity,
                        ])->all(),
                    ],
                ];
            }

            $borrowing->update(array_merge([
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? $borrowing->remarks,
                'processed_by' => auth()->id(),
            ], $receiptAttributes));

            return $borrowing;
        });

        $name = trim($updated->user?->first_name.' '.$updated->user?->last_name) ?: 'your request';
        $remarks = $data['remarks'] ?? null;
        [$title, $message] = $data['status'] === 'Approved'
            ? ['Borrowing Request Approved', "Good news, {$name}! Your borrowing request was approved. Open receipt {$updated->transaction_code} in My Requests and present its QR code to staff during your pickup window.".($remarks ? ' Note: '.$remarks : '')]
            : ['Borrowing Request Rejected', "Your borrowing request #{$updated->id} was rejected.".($remarks ? ' Reason: '.$remarks : '')];

        Notification::create([
            'user_id' => $updated->user_id,
            'title' => $title,
            'message' => $message,
            'type' => 'Borrow',
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
            'data' => $updated->load(['user', 'items.equipment', 'processor']),
        ]);
    }
}
