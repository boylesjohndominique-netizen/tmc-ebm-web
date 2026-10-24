<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessReturnRequest;
use App\Mail\ReturnStatusMail;
use App\Models\ReturnRequest;
use App\Services\ReturnApprovalService;
use Illuminate\Support\Facades\Mail;

class ReturnManagementController extends Controller
{
    public function index()
    {
        return response()->json(ReturnRequest::with(['user', 'borrowing.items.equipment', 'handoverProcessor', 'adminVerifier'])->latest()->get());
    }

    public function process(ProcessReturnRequest $request, ReturnRequest $returnRequest, ReturnApprovalService $service)
    {
        $data = $request->validated();
        $updated = $service->process($returnRequest, $data, $request->user());
        if ($updated->user?->email) {
            try {
                Mail::to($updated->user->email)->queue(new ReturnStatusMail(
                    userName: trim($updated->user->first_name),
                    status: $data['status'],
                    borrowingRequestId: $updated->borrowing_request_id,
                    remarks: $data['remarks'] ?? null,
                ));
            } catch (\Exception $e) {
                \Log::error('Failed to send return status email: '.$e->getMessage());
            }
        }

        return response()->json([
            'message' => match ($data['status']) {
                'Approved' => 'Return approved. Receipt issued; staff inspection and final admin verification are required.',
                'Confirmed' => 'Staff inspection verified. Return completed and stock restored.',
                default => 'Return rejected. The loan remains open.',
            },
            'data' => $updated->load(['user', 'borrowing.items.equipment', 'processor']),
        ]);
    }
}
