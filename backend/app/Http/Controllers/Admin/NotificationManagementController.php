<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationManagementController extends Controller
{
    public function store(Request $request)
    {
        $data=$request->validate([
            'user_id'=>'required|exists:users,id',
            'title'=>'required|string|max:150',
            'message'=>'required|string',
            'type'=>'nullable|string|max:50'
        ]);
        return response()->json(Notification::create($data),201);
    }

    public function users() { return response()->json(User::select('id','first_name','last_name','username')->orderBy('last_name')->get()); }
}
