<?php

namespace App\Http\Controllers;

use App\Models\BorrowingItem;
use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return response()->json([
                'role' => 'Administrator',
                'statistics' => [
                    'total_equipment' => Equipment::count(),
                    'available_equipment' => Equipment::sum('available_quantity'),
                    'pending_requests' => BorrowingRequest::where('status', 'Pending')->count(),
                    'overdue' => BorrowingRequest::where('status', 'Approved')
                        ->where('pickup_status', 'Received')
                        ->whereDate('expected_return_date', '<', now()->toDateString())->count(),
                    'total_users' => User::count(),
                    'pending_returns' => ReturnRequest::whereIn('status', ['Pending', 'Approved'])->count(),
                ],
                'recent_requests' => BorrowingRequest::with(['user', 'items.equipment'])
                    ->latest()->limit(8)->get(),
            ]);
        }

        return response()->json([
            'role' => $user->role?->name,
            'statistics' => [
                'active_borrowings' => BorrowingRequest::where('user_id', $user->id)->where('status', 'Approved')->where('pickup_status', 'Received')->count(),
                'pending_requests' => BorrowingRequest::where('user_id', $user->id)->where('status', 'Pending')->count(),
                'overdue' => BorrowingRequest::where('user_id', $user->id)->where('status', 'Approved')
                    ->where('pickup_status', 'Received')
                    ->whereDate('expected_return_date', '<', now()->toDateString())->count(),
                'completed' => BorrowingRequest::where('user_id', $user->id)->where('status', 'Completed')->count(),
            ],
            'recent_requests' => BorrowingRequest::with('items.equipment')->where('user_id', $user->id)
                ->latest()->limit(8)->get(),
        ]);
    }

    /**
     * Graphical analytics for the admin dashboard: monthly borrowing
     * activity, request status mix, most-borrowed equipment, stock
     * availability and user distribution.
     */
    public function analytics()
    {
        // ---- Monthly activity (last 6 months) ----
        // "month" is the month's first day as a date string; the frontend
        // formats it with the shared date style ("Apr 2026").
        $monthly = collect(range(5, 0))->map(function ($i) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = now()->subMonths($i)->endOfMonth();

            return [
                'month' => $start->toDateString(),
                'requests' => BorrowingRequest::whereBetween('created_at', [$start, $end])->count(),
                'returns' => ReturnRequest::whereBetween('created_at', [$start, $end])->count(),
            ];
        })->values();

        // ---- Request status mix ----
        $statusMix = BorrowingRequest::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['name' => $row->status, 'value' => (int) $row->total]);

        // ---- Most borrowed equipment (by units requested) ----
        $topEquipment = BorrowingItem::query()
            ->selectRaw('equipment_id, SUM(quantity) AS total')
            ->groupBy('equipment_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('equipment:id,name')
            ->get()
            ->map(fn ($item) => [
                'name' => $item->equipment?->name ?? 'Unknown',
                'value' => (int) $item->total,
            ]);

        // ---- Stock availability snapshot ----
        $totalUnits = (int) Equipment::sum('quantity');
        $availableUnits = (int) Equipment::sum('available_quantity');
        $stock = [
            ['name' => 'Available', 'value' => $availableUnits],
            ['name' => 'On loan',   'value' => max($totalUnits - $availableUnits, 0)],
        ];

        // ---- Users per role ----
        $usersByRole = User::query()
            ->with('role:id,name')
            ->get()
            ->groupBy(fn ($u) => $u->role?->name ?? 'Unassigned')
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->map(fn ($count, $role) => ['name' => $role, 'value' => $count])
            ->values();

        // ---- Headline metrics ----
        $totalRequests = BorrowingRequest::count();
        $approved = BorrowingRequest::where('status', 'Approved')->count();
        $completed = BorrowingRequest::where('status', 'Completed')->count();

        return response()->json([
            'monthly_activity' => $monthly,
            'status_mix' => $statusMix,
            'top_equipment' => $topEquipment,
            'stock' => $stock,
            'users_by_role' => $usersByRole,
            'metrics' => [
                'total_requests' => $totalRequests,
                'approval_rate' => $totalRequests ? round($approved / $totalRequests * 100) : 0,
                'completion_rate' => $totalRequests ? round($completed / $totalRequests * 100) : 0,
                'pending_returns' => ReturnRequest::where('status', 'Pending')->count(),
            ],
        ]);
    }
}
