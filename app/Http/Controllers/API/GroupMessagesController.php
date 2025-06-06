<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupMessagesController extends Controller
{
    public function index(Group $group)
    {
        $user = Auth::user();
        $isMember = $group->members()->where('user_id', $user->id)->exists();
        if ($group->owner_user_id !== $user->id && !$isMember) {
            return response()->json(['message' => '権限がありません。'], 403);
        }
        $messages = $group->messages()
            ->with('sender:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return response()->json($messages);
    }

    public function store(Group $group, Request $request)
    {
        $user = Auth::user();
        $isMember = $group->members()->where('user_id', $user->id)->exists();
        if ($group->owner_user_id !== $user->id && !$isMember) {
            return response()->json(['message' => '権限がありません。'], 403);
        }
        $data = $request->validate([
            'message' => 'required|string|max:5000',
        ]);
        $msg = $group->messages()->create([
            'sender_user_id' => $user->id,
            'message' => $data['message'],
            'target_type' => 'all',
            'created_at' => now(),
        ]);
        $msg->load('sender:id,name');
        return response()->json($msg, 201);
    }
}
