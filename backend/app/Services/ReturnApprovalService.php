<?php

namespace App\Services;

use App\Models\BorrowingRequest;
use App\Models\Equipment;
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
            if ($return->status === 'Awaiting Verification') {
                return $this->verify($return, $borrowing, $data, $admin);
            }
            abort_unless($return->status === 'Pending', 409, 'This return request has already been reviewed.');
            abort_if($data['status'] === 'Confirmed', 409, 'Staff must submit their inspection and decision before administrator verification.');
            $attributes = ['status' => $data['status'], 'processed_by' => $admin->id];

            if ($data['status'] === 'Approved') {
                abort_unless($borrowing->status === 'Approved' && $borrowing->pickup_status === 'Received', 409,
                    'Only equipment received by the student and still on loan can be returned.');
                abort_if($borrowing->returns()->whereIn('status', ['Approved', 'Awaiting Verification'])->exists(), 409,
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
                        'pickup_location' => $item->equipment?->pickup_location,
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

    private function verify(ReturnRequest $return, BorrowingRequest $borrowing, array $data, User $admin): ReturnRequest
    {
        abort_unless(in_array($data['status'], ['Confirmed', 'Rejected'], true), 422, 'Choose a final verification decision.');
        abort_unless($borrowing->status === 'Approved' && $borrowing->pickup_status === 'Received', 409, 'This loan has already been completed or is not eligible for return.');
        $confirmed = $data['status'] === 'Confirmed';
        if ($confirmed) {
            abort_unless($return->handover_status === 'Accepted' && $return->condition_on_return === 'Good' &&
                ($return->receipt_snapshot['equipment_checked'] ?? false) === true && ! empty($return->receipt_snapshot['verified_student_id']) &&
                ((float) $return->overdue_fee <= 0 || $return->overdue_fee_paid === true), 422,
                'Only staff-accepted, inspected equipment with any overdue fee paid can be confirmed.');
            foreach ($borrowing->items()->orderBy('equipment_id')->get() as $item) {
                $equipment = Equipment::lockForUpdate()->findOrFail($item->equipment_id);
                $equipment->increment('available_quantity', $item->quantity);
            }
            $borrowing->update(['status' => 'Completed']);
        }
        $return->update([
            'status' => $data['status'],
            'returned_at' => $confirmed ? $return->handover_processed_at : null,
            'admin_verified_at' => now(),
            'admin_verified_by' => $admin->id,
            'admin_remarks' => $data['remarks'] ?? null,
            'receipt_snapshot' => array_merge($return->receipt_snapshot ?? [], ['admin_verifier_name' => trim($admin->first_name.' '.$admin->last_name)]),
        ]);
        $recipients = collect([$return->user_id, $return->handover_processed_by])->filter()->unique();
        foreach ($recipients as $userId) {
            Notification::create([
                'user_id' => $userId,
                'title' => $confirmed ? 'Equipment Return Verified' : 'Return Rejection Verified',
                'message' => "Return receipt {$return->transaction_code}: the administrator ".($confirmed
                    ? 'verified the staff inspection and completed the return.'
                    : 'rejected the return. Reason: '.$data['remarks']).($confirmed && ! empty($data['remarks']) ? ' '.$data['remarks'] : ''),
                'type' => 'Return',
            ]);
        }

        return $return;
    }
}
