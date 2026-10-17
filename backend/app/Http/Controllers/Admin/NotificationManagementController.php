<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotificationManagementController extends Controller
{
    /**
     * Send a notification: to one user, or — when `all` is true — to every
     * user in one bulk insert, so a broadcast to hundreds of accounts stays
     * a single round of queries.
     */
    public function store(Request $request)
    {
        $data=$request->validate([
            'user_id'=>'nullable|integer|exists:users,id',
            'title'=>'required|string|max:150',
            'message'=>'required|string',
            'type'=>'nullable|string|max:50',
            'all'=>'sometimes|boolean',
        ]);

        $sendToAll = $request->boolean('all');

        if (! $sendToAll && empty($data['user_id'])) {
            throw ValidationException::withMessages([
                'user_id' => 'Choose a recipient, or enable "send to all users".',
            ]);
        }

        $type = $data['type'] ?? 'System';

        if ($sendToAll) {
            $now = now();
            $count = 0;
            User::query()->select('id')->chunkById(500, function ($users) use ($data, $type, $now, &$count) {
                $rows = $users->map(fn ($u) => [
                    'user_id' => $u->id,
                    'title' => $data['title'],
                    'message' => $data['message'],
                    'type' => $type,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                Notification::insert($rows);
                $count += count($rows);
            });

            return response()->json([
                'message' => "Notification sent to {$count} user(s).",
                'count' => $count,
            ], 201);
        }

        $notification = Notification::create([
            'user_id' => $data['user_id'],
            'title' => $data['title'],
            'message' => $data['message'],
            'type' => $type,
        ]);

        return response()->json($notification, 201);
    }

    public function users() { return response()->json(User::select('id','first_name','last_name','username')->orderBy('last_name')->get()); }
}
