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
        $this->expire($this->find($data['code'])->id);

        return DB::transaction(function () use ($data, $staff) {
            $borrowing = $this->find($data['code'], true);
            abort_if($borrowing->receipt_snapshot['pickup_expired'] ?? false, 409,
                'The pickup time has ended. The student must submit a new borrowing request for another pickup time.');
            abort_unless($borrowing->status === 'Approved' && $borrowing->pickup_status === 'Awaiting', 409,
                'This receipt is not awaiting pickup or has already been processed.');

            if ($data['method'] === 'qr' && ! str_starts_with($data['code'], 'TMCEBMS:')) {
                throw ValidationException::withMessages(['code' => 'Scan the QR code on an approved receipt to review pickup.']);
            }

            abort_unless($borrowing->pickup_ends_at && now()->lt($borrowing->pickup_ends_at), 409,
                'The pickup time has ended. The student must submit a new borrowing request for another pickup time.');

            if ($data['status'] === 'Rejected') {
                $this->releaseStock($borrowing);
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

    public function expireDue(): int
    {
        $expired = 0;
        BorrowingRequest::where('status', 'Approved')->where('pickup_status', 'Awaiting')
            ->where('pickup_ends_at', '<=', now())->select('id')
            ->chunkById(100, function ($borrowings) use (&$expired) {
                foreach ($borrowings as $borrowing) {
                    $expired += (int) $this->expire($borrowing->id);
                }
            });

        return $expired;
    }

    public function expire(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $borrowing = BorrowingRequest::lockForUpdate()->find($id);
            if (! $borrowing || $borrowing->status !== 'Approved' || $borrowing->pickup_status !== 'Awaiting'
                || ! $borrowing->pickup_ends_at || now()->lt($borrowing->pickup_ends_at)) {
                return false;
            }

            $reason = 'Your pickup time has ended. You can no longer pick up this equipment with this receipt. Please submit a new borrowing request for another pickup time.';
            $this->releaseStock($borrowing);
            $borrowing->update([
                'status' => 'Rejected',
                'pickup_status' => 'Rejected',
                'pickup_processed_at' => now(),
                'pickup_processed_by' => null,
                'pickup_remarks' => $reason,
                'receipt_snapshot' => array_merge($borrowing->receipt_snapshot ?? [], [
                    'pickup_expired' => true,
                    'pickup_method' => 'system',
                ]),
            ]);
            Notification::create([
                'user_id' => $borrowing->user_id,
                'title' => 'Pickup Time Expired',
                'message' => "Receipt {$borrowing->transaction_code}: ".$reason,
                'type' => 'Borrow',
            ]);

            return true;
        });
    }

    private function releaseStock(BorrowingRequest $borrowing): void
    {
        foreach ($borrowing->items()->orderBy('equipment_id')->get() as $item) {
            $equipment = Equipment::lockForUpdate()->findOrFail($item->equipment_id);
            $equipment->increment('available_quantity', $item->quantity);
        }
    }
}
