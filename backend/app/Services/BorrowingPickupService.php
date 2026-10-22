<?php

namespace App\Services;

use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BorrowingPickupService
{
    public function find(string $code, bool $lock = false): BorrowingRequest
    {
        $query = BorrowingRequest::query();
        if (preg_match('/^TMCEBMS:([a-f0-9]{64})$/', $code, $matches)) {
            $query->where('qr_token', $matches[1]);
        } else {
            $query->where('transaction_code', $code);
        }

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    public function process(array $data, User $staff): BorrowingRequest
    {
        return DB::transaction(function () use ($data, $staff) {
            $borrowing = $this->find($data['code'], true);
            abort_unless($borrowing->status === 'Approved' && $borrowing->pickup_status === 'Awaiting', 409,
                'This receipt is not awaiting pickup or has already been processed.');

            if ($data['method'] === 'qr' && ! str_starts_with($data['code'], 'TMCEBMS:')) {
                throw ValidationException::withMessages(['code' => 'Scan the QR code on an approved receipt to review pickup.']);
            }

            if ($data['status'] === 'Received') {
                abort_unless($borrowing->pickup_starts_at && $borrowing->pickup_ends_at &&
                    now()->betweenIncluded($borrowing->pickup_starts_at, $borrowing->pickup_ends_at), 409,
                    'Equipment can only be received during the approved pickup window.');
            } else {
                $items = $borrowing->items()->orderBy('equipment_id')->get();
                foreach ($items as $item) {
                    $equipment = Equipment::lockForUpdate()->findOrFail($item->equipment_id);
                    $equipment->increment('available_quantity', $item->quantity);
                }
            }

            $borrowing->update([
                'status' => $data['status'] === 'Rejected' ? 'Rejected' : 'Approved',
                'pickup_status' => $data['status'],
                'pickup_processed_at' => now(),
                'pickup_processed_by' => $staff->id,
                'pickup_remarks' => $data['remarks'] ?? null,
                'receipt_snapshot' => array_merge($borrowing->receipt_snapshot ?? [], [
                    'pickup_staff_name' => trim($staff->first_name.' '.$staff->last_name),
                    'pickup_method' => $data['method'],
                ]),
            ]);

            $received = $data['status'] === 'Received';
            $title = $received ? 'Equipment Pickup Confirmed' : 'Equipment Pickup Rejected';
            $message = "Receipt {$borrowing->transaction_code}: ".($received
                ? 'equipment received by the student.'
                : 'equipment was not received. Reason: '.$data['remarks']);
            $recipients = User::whereHas('role', fn ($q) => $q->where('name', 'Administrator'))
                ->orWhere('is_super_admin', true)->pluck('id')->push($borrowing->user_id)->unique();

            foreach ($recipients as $userId) {
                Notification::create([
                    'user_id' => $userId,
                    'title' => $title,
                    'message' => $message,
                    'type' => 'Borrow',
                ]);
            }

            return $borrowing;
        });
    }
}
