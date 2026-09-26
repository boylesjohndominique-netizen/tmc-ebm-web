<?php

namespace App\Http\Controllers;

use App\Models\BorrowingRequest;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Audit trail of processed (confirmed/declined) borrowing and return
     * requests. Records move here once an administrator approves, rejects,
     * confirms or rejects them, so only non-pending rows are listed.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;

        // Cancelled requests are student withdrawals, not admin decisions,
        // so they are excluded from the audit trail.
        $borrowings = BorrowingRequest::with(['user', 'items.equipment', 'processor'])
            ->whereIn('status', ['Approved', 'Rejected'])
            ->when($from, fn ($q) => $q->whereDate('updated_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('updated_at', '<=', $to))
            ->latest('updated_at')
            ->get();

        $returns = ReturnRequest::with(['user', 'borrowing.items.equipment', 'processor'])
            ->whereIn('status', ['Confirmed', 'Rejected'])
            ->when($from, fn ($q) => $q->whereDate('updated_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('updated_at', '<=', $to))
            ->latest('updated_at')
            ->get();

        return response()->json([
            'borrowings' => $borrowings,
            'returns' => $returns,
        ]);
    }
}
