<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BorrowingItem;
use App\Models\Equipment;
use Illuminate\Http\Request;

class EquipmentManagementController extends Controller
{
    public function store(Request $request)
    {
        $data=$request->validate([
            'category_id'=>'required|exists:categories,id','name'=>'required|string|max:150',
            'description'=>'nullable|string','quantity'=>'required|integer|min:0',
            'condition'=>'required|string|max:50'
        ]);
        $data['available_quantity']=$data['quantity'];
        $data['status']=$data['quantity']>0?'Available':'Unavailable';
        return response()->json(Equipment::create($data)->load('category'),201);
    }

    public function show(Equipment $equipment)
    {
        $items = BorrowingItem::with('borrowing.user')
            ->where('equipment_id',$equipment->id)
            ->whereHas('borrowing', fn ($q) => $q->whereIn('status', ['Pending','Approved']))
            ->latest()->get();

        return response()->json([
            'equipment' => $equipment->load('category'),
            'active_borrowings' => $items->map(fn ($i) => [
                'borrowing_request_id' => $i->borrowing_request_id,
                'borrower' => trim("{$i->borrowing?->user?->first_name} {$i->borrowing?->user?->last_name}"),
                'quantity' => $i->quantity,
                'status' => $i->borrowing?->status,
                'expected_return_date' => $i->borrowing?->expected_return_date,
            ]),
        ]);
    }

    public function update(Request $request, Equipment $equipment)
    {
        $data=$request->validate([
            'category_id'=>'required|exists:categories,id','name'=>'required|string|max:150',
            'description'=>'nullable|string','quantity'=>'required|integer|min:0','condition'=>'required|string|max:50'
        ]);
        $borrowed=$equipment->quantity-$equipment->available_quantity;
        if ($data['quantity'] < $borrowed) return response()->json(['message'=>'Quantity cannot be lower than currently borrowed quantity.'],422);
        $data['available_quantity']=$data['quantity']-$borrowed;
        $data['status']=$data['available_quantity']>0?'Available':'Unavailable';
        $equipment->update($data);
        return response()->json($equipment->fresh()->load('category'));
    }

    public function destroy(Equipment $equipment)
    {
        $activeBorrowings = BorrowingItem::where('equipment_id',$equipment->id)
            ->whereHas('borrowing', fn ($q) => $q->whereIn('status', ['Pending','Approved']))
            ->count();

        if ($activeBorrowings > 0) {
            return response()->json([
                'message' => "Cannot delete: {$equipment->name} is involved in {$activeBorrowings} pending/approved borrowing(s). Resolve them first.",
            ], 422);
        }

        $equipment->delete();
        return response()->json(['message'=>'Equipment deleted.']);
    }
}
