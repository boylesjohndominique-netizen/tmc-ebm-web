<?php

namespace App\Services;

use App\Models\BorrowingRequest;
use App\Models\Notification;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnHandoverService
{
    public function find(string $code): ReturnRequest
    {
        $query = ReturnRequest::query();
        if (preg_match('/^TMCEBMS:RETURN:([a-f0-9]{64})$/', $code, $matches)) {
            $query->where('qr_token', $matches[1]);
        } else {
            $query->where('transaction_code', $code);
        }

        return $query->firstOrFail();
    }

    public function process(array $data, User $staff): ReturnRequest
    {
        $candidate = $this->find($data['code']);

        return DB::transaction(function () use ($candidate, $data, $staff) {
            $borrowing = BorrowingRequest::lockForUpdate()->findOrFail($candidate->borrowing_request_id);
            $return = ReturnRequest::lockForUpdate()->findOrFail($candidate->id);
            abort_unless($return->status === 'Approved' && $return->handover_status === 'Awaiting' &&
                $borrowing->status === 'Approved' && $borrowing->pickup_status === 'Received', 409,
                'This return receipt is not awaiting inspection or has already been processed.');
            $accepted = $data['status'] === 'Accepted';
            if ($data['method'] === 'qr' && ! str_starts_with($data['code'], 'TMCEBMS:RETURN:')) {
                throw ValidationException::withMessages(['code' => 'Scan a return receipt QR code to record an inspection.']);
            }
            $fee = app(ReturnFeeService::class)->quote($borrowing);
            abort_if(abs((float) $data['quoted_overdue_fee'] - $fee['overdue_fee']) > 0.005, 409,
                'The overdue fee has changed. Find or scan the receipt again and verify payment against the updated amount.');
            $verification = [];
            $attributes = [
                'status' => 'Awaiting Verification',
                'condition_on_return' => $data['condition_on_return'],
                'condition_notes' => $data['condition_notes'] ?? null,
                'overdue_fee_paid' => $fee['overdue_fee'] > 0 ? (bool) $data['overdue_fee_paid'] : null,
                'overdue_days' => $fee['overdue_days'],
                'overdue_fee' => $fee['overdue_fee'],
                'handover_status' => $data['status'],
                'handover_processed_at' => now(),
                'handover_processed_by' => $staff->id,
                'handover_remarks' => $data['remarks'] ?? null,
            ];
            if ($accepted) {
                abort_unless($return->handover_starts_at && $return->handover_ends_at &&
                    now()->betweenIncluded($return->handover_starts_at, $return->handover_ends_at), 409,
                    'Returns can only be accepted during the approved return window.');
                $verification = app(HandoverVerificationService::class)->verify(
                    $return->receipt_snapshot['borrower']['id_number'] ?? $borrowing->user?->id_number,
                    $data
                );
                abort_unless($data['condition_on_return'] === 'Good', 422, 'Reject damaged or incomplete equipment and record the problem.');
                abort_if($fee['overdue_fee'] > 0 && ! $attributes['overdue_fee_paid'], 422,
                    'The overdue fee is not paid. Record a rejected return with the payment reason.');
            }
            $attributes['receipt_snapshot'] = array_merge($return->receipt_snapshot ?? [], $verification, [
                'handover_staff_name' => trim($staff->first_name.' '.$staff->last_name),
                'handover_method' => $data['method'],
            ]);
            $return->update($attributes);
            $title = 'Return Inspection Awaiting Verification';
            $message = "Return receipt {$return->transaction_code}: staff {$data['status']} the return. "
                ."Condition: {$data['condition_on_return']}. ".($data['condition_notes'] ?? '')
                .sprintf(' Overdue fee: PHP %.2f (%s).', $return->overdue_fee, $return->payment_status)
                .(! empty($data['remarks']) ? ' Reason: '.$data['remarks'] : '')
                .' Awaiting final administrator verification; the loan is still open.';
            $recipients = User::whereHas('role', fn ($q) => $q->where('name', 'Administrator'))
                ->orWhere('is_super_admin', true)->pluck('id')->push($return->user_id)->unique();
            foreach ($recipients as $userId) {
                Notification::create(['user_id' => $userId, 'title' => $title, 'message' => $message, 'type' => 'Return']);
            }

            return $return;
        });
    }
}
