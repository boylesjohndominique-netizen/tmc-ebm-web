<?php

namespace App\Services;

use App\Models\BorrowingRequest;
use App\Models\Setting;
use Illuminate\Support\Carbon;

class ReturnFeeService
{
    public function quote(BorrowingRequest $borrowing): array
    {
        $expected = Carbon::parse($borrowing->expected_return_date->toDateString(), 'Asia/Manila')->startOfDay();
        $actual = now('Asia/Manila')->startOfDay();
        $days = max(0, (int) $expected->diffInDays($actual, false));
        $rate = max(0, (float) Setting::get(Setting::OVERDUE_FEE_PER_DAY, 0));

        return ['overdue_days' => $days, 'overdue_fee' => round($days * $rate, 2)];
    }
}
