<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Admin-editable system settings: the per-day overdue fee and the
 * platform policies (terms & conditions, privacy policy, and other
 * guidelines) authored by administrators.
 */
class SettingController extends Controller
{
    /** Current settings. */
    public function index()
    {
        return response()->json([
            'overdue_fee_per_day' => (float) Setting::get(Setting::OVERDUE_FEE_PER_DAY, 0),
            'platform_policies' => $this->policies(),
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

    /** Replace the full list of platform policies. */
    public function updatePolicies(Request $request)
    {
        $data = $request->validate([
            'policies' => ['present', 'array', 'max:50'],
            'policies.*.id' => ['nullable', 'string', 'max:64'],
            'policies.*.title' => ['required', 'string', 'max:150'],
            'policies.*.content' => ['required', 'string', 'max:20000'],
        ]);

        $policies = collect($data['policies'])->values()->map(fn ($policy) => [
            'id' => $policy['id'] ?? (string) Str::uuid(),
            'title' => trim($policy['title']),
            'content' => trim($policy['content']),
        ])->all();

        Setting::set(Setting::PLATFORM_POLICIES, json_encode($policies));

        return response()->json([
            'message' => 'Platform policies saved.',
            'platform_policies' => $policies,
        ]);
    }

    /** Decode the stored platform policies, guarding against bad JSON. */
    private function policies(): array
    {
        $raw = Setting::get(Setting::PLATFORM_POLICIES);
        $decoded = $raw ? json_decode($raw, true) : [];

        return is_array($decoded) ? $decoded : [];
    }
}
