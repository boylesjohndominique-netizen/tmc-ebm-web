<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Admin-editable system settings. Currently a single value: the overdue
 * fee charged for every day a borrowed item is returned past its
 * expected return date.
 */
class SettingController extends Controller
{
    /** Current settings. */
    public function index()
    {
        return response()->json([
            'overdue_fee_per_day' => (float) Setting::get(Setting::OVERDUE_FEE_PER_DAY, 0),
        ]);
    }

    /** Update the overdue fee per day. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'overdue_fee_per_day' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ]);

        Setting::set(Setting::OVERDUE_FEE_PER_DAY, $data['overdue_fee_per_day']);

        return response()->json([
            'message' => 'Settings saved.',
            'overdue_fee_per_day' => (float) Setting::get(Setting::OVERDUE_FEE_PER_DAY),
        ]);
    }
}
