<?php

namespace App\Services;

use App\Models\BorrowingRequest;
use App\Models\Notification;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReturnApprovalService
{
    public function process(ReturnRequest $return, array $data, User $admin): ReturnRequest
    {
        return DB::transaction(function () use ($return, $data, $admin) {
            $borrowing = BorrowingRequest::lockForUpdate()->findOrFail($return->borrowing_request_id);
            $return = ReturnRequest::lockForUpdate()->findOrFail($return->id);
            abort_unless($return->status === 'Pending', 409, 'This return request has already been reviewed.');
            $attributes = ['status' => $data['status'], 'processed_by' => $admin->id];

            if ($data['status'] === 'Approved') {
                abort_unless($borrowing->status === 'Approved' && $borrowing->pickup_status === 'Received', 409,
                    'Only equipment received by the student and still on loan can be returned.');
                abort_if($borrowing->returns()->where('status', 'Approved')->exists(), 409,
                    'Another return receipt is already awaiting staff inspection.');
                $borrowing->load(['user', 'items.equipment.category']);
                $borrower = $borrowing->user;
                $snapshot = $borrowing->receipt_snapshot ?? [
                    'borrowing_date' => $borrowing->borrowing_date->toDateString(),
                    'expected_return_date' => $borrowing->expected_return_date->toDateString(),
                    'borrower' => [
                        'name' => trim($borrower->first_name.' '.$borrower->last_name),
                        'id_number' => $borrower->id_number,
                        'email' => $borrower->email,
                        'contact_number' => $borrower->contact_number,
                    ],
                    'items' => $borrowing->items->map(fn ($item) => [
                        'equipment_id' => $item->equipment_id,
                        'name' => $item->equipment?->name,
                        'description' => $item->equipment?->description,
                        'category' => $item->equipment?->category?->name,
                        'condition' => $item->equipment?->condition,
                        'quantity' => $item->quantity,
                    ])->all(),
                ];
                $attributes += [
                    'approved_at' => now(),
                    'handover_starts_at' => Carbon::parse($data['handover_starts_at'])->utc(),
                    'handover_ends_at' => Carbon::parse($data['handover_ends_at'])->utc(),
                    'handover_status' => 'Awaiting',
                    'receipt_snapshot' => array_merge($snapshot, [
                        'request_id' => $return->id,
                        'borrowing_request_id' => $borrowing->id,
                        'borrowing_transaction_code' => $borrowing->transaction_code,
                        'requested_at' => $return->created_at->toISOString(),
                        'borrower_remarks' => $return->remarks,
                        'approval_remarks' => $data['remarks'] ?? null,
                        'approved_by' => trim($admin->first_name.' '.$admin->last_name),
                    ]),
                ];
                foreach (['pickup_staff_name', 'pickup_method', 'verified_student_id', 'equipment_checked'] as $field) {
                    unset($attributes['receipt_snapshot'][$field]);
                }
            } else {
                $attributes['handover_remarks'] = $data['remarks'];
            }

            $return->update($attributes);
            Notification::create([
                'user_id' => $return->user_id,
                'title' => $data['status'] === 'Approved' ? 'Return Request Approved' : 'Return Request Rejected',
                'message' => $data['status'] === 'Approved'
                    ? "Return receipt {$return->transaction_code} is ready. Present its QR code and your student ID to staff during the return window. Only staff can confirm the equipment was returned."
                    : 'Your return request was rejected. Reason: '.$data['remarks'],
                'type' => 'Return',
            ]);

            return $return;
        });
    }
}
