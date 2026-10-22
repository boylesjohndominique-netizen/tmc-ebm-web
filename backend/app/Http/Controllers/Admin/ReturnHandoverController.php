<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessReturnHandoverRequest;
use App\Http\Resources\ReturnReceiptResource;
use App\Services\ReturnHandoverService;
use Illuminate\Http\Request;

class ReturnHandoverController extends Controller
{
    public function show(Request $request, ReturnHandoverService $service): ReturnReceiptResource
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^(\d{12}|TMCEBMS:RETURN:[a-f0-9]{64})$/'],
        ]);
        $return = $service->find($data['code']);
        abort_unless($return->receipt_snapshot, 404, 'This return request does not have an approval receipt.');

        return new ReturnReceiptResource($return);
    }

    public function process(ProcessReturnHandoverRequest $request, ReturnHandoverService $service)
    {
        $return = $service->process($request->validated(), $request->user());

        return response()->json([
            'message' => $return->status === 'Confirmed' ? 'Return inspected and confirmed. Equipment restocked and admin notified.' : 'Return rejected and admin notified. The loan remains open.',
            'data' => new ReturnReceiptResource($return),
        ]);
    }
}
