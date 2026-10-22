<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessPickupRequest;
use App\Http\Resources\BorrowingReceiptResource;
use App\Services\BorrowingPickupService;
use Illuminate\Http\Request;

class BorrowingPickupController extends Controller
{
    public function show(Request $request, BorrowingPickupService $service): BorrowingReceiptResource
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^(\d{12}|TMCEBMS:[a-f0-9]{64})$/'],
        ]);
        $borrowing = $service->find($data['code']);
        abort_unless($borrowing->receipt_snapshot, 404, 'An approval receipt has not been issued for this request.');
        $service->expire($borrowing->id);
        $borrowing->refresh();

        return new BorrowingReceiptResource($borrowing);
    }

    public function process(ProcessPickupRequest $request, BorrowingPickupService $service)
    {
        $borrowing = $service->process($request->validated(), $request->user());

        return response()->json([
            'message' => $borrowing->pickup_status === 'Received' ? 'Equipment pickup confirmed.' : 'Pickup rejected; reserved stock released.',
            'data' => new BorrowingReceiptResource($borrowing),
        ]);
    }
}
