<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupMessageController extends Controller
{
    public function index(Group $group)
    {
        $user = Auth::user();
        if (!$group->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => __('errors.forbidden')], 403);
        }
        $messages = $group->messages()->with('sender:id,name')->orderBy('created_at', 'desc')->paginate(20);
        return response()->json($messages);
    }

    public function store(Group $group, Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);
        $user = Auth::user();
        if (!$group->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => __('errors.forbidden')], 403);
        }
        $msg = GroupMessage::create([
            'group_id' => $group->id,
            'sender_user_id' => $user->id,
            'message' => $request->message,
            'target_type' => 'all',
        ]);
        return response()->json($msg, 201);
    }
}
