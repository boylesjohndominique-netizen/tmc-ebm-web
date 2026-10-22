<?php

namespace App\Services;

use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\Notification;
use App\Models\ReturnRequest;
use App\Models\Setting;
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
            $confirmed = $data['status'] === 'Confirmed';
            if ($data['method'] === 'qr' && (! str_starts_with($data['code'], 'TMCEBMS:RETURN:') || ! $confirmed)) {
                throw ValidationException::withMessages(['code' => 'Scan an approved return receipt QR code to confirm a return.']);
            }
            $verification = [];
            $attributes = [
                'status' => $data['status'],
                'condition_on_return' => $data['condition_on_return'],
                'handover_status' => $confirmed ? 'Received' : 'Rejected',
                'handover_processed_at' => now(),
                'handover_processed_by' => $staff->id,
                'handover_remarks' => $data['remarks'] ?? null,
            ];
            if ($confirmed) {
                abort_unless($return->handover_starts_at && $return->handover_ends_at &&
                    now()->betweenIncluded($return->handover_starts_at, $return->handover_ends_at), 409,
                    'Returns can only be confirmed during the approved return window.');
                $verification = app(HandoverVerificationService::class)->verify($return->receipt_snapshot['borrower']['id_number'] ?? null, $data);
                abort_unless($data['condition_on_return'] === 'Good', 422, 'Damaged or incomplete equipment cannot be confirmed or restocked.');
                foreach ($borrowing->items()->orderBy('equipment_id')->get() as $item) {
                    $equipment = Equipment::lockForUpdate()->findOrFail($item->equipment_id);
                    $equipment->increment('available_quantity', $item->quantity);
                }
                $borrowing->update(['status' => 'Completed']);
                $expected = $borrowing->expected_return_date->copy()->startOfDay();
                $actual = now()->startOfDay();
                $overdueDays = max(0, (int) $expected->diffInDays($actual, false));
                $attributes += [
                    'returned_at' => now(),
                    'overdue_days' => $overdueDays,
                    'overdue_fee' => round($overdueDays * (float) Setting::get(Setting::OVERDUE_FEE_PER_DAY, 0), 2),
                ];
            }
            $attributes['receipt_snapshot'] = array_merge($return->receipt_snapshot ?? [], $verification, [
                'handover_staff_name' => trim($staff->first_name.' '.$staff->last_name),
                'handover_method' => $data['method'],
            ]);
            $return->update($attributes);
            $title = $confirmed ? 'Equipment Return Confirmed' : 'Equipment Return Rejected';
            $message = "Return receipt {$return->transaction_code}: ".($confirmed
                ? "{$staff->first_name} {$staff->last_name} verified the student ID and received all equipment in good condition."
                : "staff did not accept the return ({$data['condition_on_return']}). Reason: {$data['remarks']}. The loan remains open.");
            if ($confirmed && (float) $return->overdue_fee > 0) {
                $message .= sprintf(' Overdue fee: PHP %.2f (%d day(s)).', $return->overdue_fee, $return->overdue_days);
            }
            $recipients = User::whereHas('role', fn ($q) => $q->where('name', 'Administrator'))
                ->orWhere('is_super_admin', true)->pluck('id')->push($return->user_id)->unique();
            foreach ($recipients as $userId) {
                Notification::create(['user_id' => $userId, 'title' => $title, 'message' => $message, 'type' => 'Return']);
            }

            return $return;
        });
    }
}
