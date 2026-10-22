<?php

namespace App\Http\Resources;

use App\Services\ReturnFeeService;
use Illuminate\Http\Request;

class ReturnReceiptResource extends BorrowingReceiptResource
{
    public function toArray(Request $request): array
    {
        $receipt = parent::toArray($request);
        if ($this->resource->status === 'Approved' && $this->resource->handover_status === 'Awaiting') {
            $receipt['fee_quote'] = app(ReturnFeeService::class)->quote($this->resource->borrowing);
        }

        return $receipt;
    }
}
