<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Data source for the printable reports page. Each key feeds one
     * selectable report (summary, users, borrowings, returns); the audit
     * report is served separately by /admin/transactions.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;

        $borrowings = BorrowingRequest::with(['user', 'items.equipment'])
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()->get();

        $returns = ReturnRequest::with(['user', 'borrowing.items.equipment'])
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()->get();

        $users = User::with('role')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        return response()->json([
            'summary' => [
                'users' => $users->count(),
                'equipment' => Equipment::count(),
                'borrowings' => $borrowings->count(),
                'returns' => $returns->count(),
                'overdue' => BorrowingRequest::where('status', 'Approved')
                    ->whereDate('expected_return_date', '<', now()->toDateString())->count(),
            ],
            'users' => $users,
            'borrowings' => $borrowings,
            'returns' => $returns,
        ]);
    }
}
