<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserAppeal;
use Illuminate\Http\Request;

/**
 * Public ban-appeal intake for the mobile app.
 *
 * A banned user cannot call authenticated endpoints (their tokens are
 * revoked), so this endpoint identifies the sender by username/email and
 * only accepts the appeal when that account is actually banned. The
 * response is intentionally generic so anonymous callers cannot use it to
 * probe which accounts exist.
 */
class AppealController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $user = User::where('username', $data['login'])
            ->orWhere('email', $data['login'])
            ->first();

        if ($user && $user->isBanned()) {
            UserAppeal::create([
                'user_id' => $user->id,
                'message' => $data['message'],
            ]);

            // Drop the appeal into every administrator's in-app feed so it
            // surfaces in the admin Notifications page for review.
            $name = trim($user->first_name.' '.$user->last_name);
            $now = now();
            $adminIds = User::where('is_super_admin', true)
                ->orWhereHas('role', fn ($q) => $q->where('name', 'Administrator'))
                ->pluck('id');

            $records = $adminIds->map(fn ($adminId) => [
                'user_id' => $adminId,
                'title' => 'Ban Appeal — '.($name !== '' ? $name : $user->username),
                'message' => 'Reason given by the user: '.$data['message'],
                'type' => 'Account',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if (! empty($records)) {
                Notification::insert($records);
            }
        }

        return response()->json([
            'message' => 'Your appeal has been sent to the administrator for review.',
        ]);
    }
}
