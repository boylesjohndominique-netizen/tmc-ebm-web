<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(Notification::where('user_id',$request->user()->id)->latest()->get());
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => Notification::where('user_id',$request->user()->id)->whereNull('read_at')->count(),
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
}
