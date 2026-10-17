<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(Notification::where('user_id',$request->user()->id)->latest()->get());
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => Notification::where('user_id',$request->user()->id)
                ->whereNull('read_at')
                ->whereNull('archived_at')
                ->whereNull('hidden_at')
                ->count(),
        ]);
    }

    public function read(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) abort(403);
        $notification->update(['read_at'=>now()]);
        return response()->json(['message'=>'Notification marked as read.']);
    }

    public function readAll(Request $request)
    {
        Notification::where('user_id',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now()]);
        return response()->json(['message'=>'All notifications marked as read.']);
    }

    /**
     * Apply one action (read / archive / unarchive / hide / unhide / delete)
     * to a set of the caller's own notifications. Powers both per-row and
     * multi-select bulk controls in the admin inbox.
     */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['read','archive','unarchive','hide','unhide','delete'])],
            'ids' => ['required','array','min:1'],
            'ids.*' => ['integer'],
        ]);

        $scope = fn () => Notification::where('user_id', $request->user()->id)->whereIn('id', $data['ids']);
        $now = now();
        $count = 0;

        switch ($data['action']) {
            case 'read':
                $count = $scope()->whereNull('read_at')->update(['read_at' => $now]);
                break;
            case 'archive':
                $scope()->whereNull('read_at')->update(['read_at' => $now]);
                $count = $scope()->update(['archived_at' => $now, 'hidden_at' => null]);
                break;
            case 'unarchive':
                $count = $scope()->update(['archived_at' => null]);
                break;
            case 'hide':
                $scope()->whereNull('read_at')->update(['read_at' => $now]);
                $count = $scope()->update(['hidden_at' => $now]);
                break;
            case 'unhide':
                $count = $scope()->update(['hidden_at' => null]);
                break;
            case 'delete':
                $count = $scope()->delete();
                break;
        }

        return response()->json([
            'message' => ucfirst($data['action'])." applied to {$count} notification(s).",
            'count' => $count,
        ]);
    }

    /** Archive every read notification currently sitting in the inbox. */
    public function archiveRead(Request $request)
    {
        $now = now();
        $base = fn () => Notification::where('user_id', $request->user()->id)
            ->whereNull('archived_at')->whereNull('hidden_at');

        $base()->whereNull('read_at')->update(['read_at' => $now]);
        $count = $base()->whereNotNull('read_at')->update(['archived_at' => $now]);

        return response()->json([
            'message' => "Archived {$count} read notification(s).",
            'count' => $count,
        ]);
    }

    /** Permanently delete every archived notification. */
    public function clearArchived(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNotNull('archived_at')->delete();

        return response()->json([
            'message' => "Deleted {$count} archived notification(s).",
            'count' => $count,
        ]);
    }
}
