<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user()->load('role'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'contact_number' => ['nullable', 'string', 'max:30'],
        ]);
        $user->update($data);

        return response()->json($user->fresh()->load('role'));
    }

    public function destroyAvatar(Request $request)
    {
        $user = $request->user();
        $previousPath = $user->profile_photo_path;
        $user->update(['profile_photo_path' => null]);
        if ($previousPath) {
            Storage::disk('local')->delete($previousPath);
        }

        return response()->json([
            'message' => 'Profile picture removed.',
            'user' => $user->fresh()->load('role'),
        ]);
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            // 'current_password:web' targets the 'web' guard, which does not exist
            // for Sanctum token APIs and always fails. Hash::check is guard-safe.
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'The current password is incorrect.'], 422);
        }

        $user->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
