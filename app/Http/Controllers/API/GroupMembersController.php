<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupMembersController extends Controller
{
    public function store(Group $group, Request $request)
    {
        $user = Auth::user();
        if ($group->owner_user_id !== $user->id) {
            return response()->json(['message' => '権限がありません。'], 403);
        }
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'nickname' => 'required|string|max:50',
        ]);
        $member = $group->members()->create([
            'user_id' => $data['user_id'],
            'nickname' => $data['nickname'],
            'joined_at' => now(),
        ]);
        return response()->json($member, 201);
    }

    public function destroy(Group $group, GroupMember $member)
    {
        $user = Auth::user();
        if ($group->owner_user_id !== $user->id) {
            return response()->json(['message' => '権限がありません。'], 403);
        }
        if ($member->group_id !== $group->id) {
            return response()->json(['message' => '不正なメンバー指定です。'], 400);
        }
        $member->delete();
        return response()->json(['message' => 'removed']);
    }
}
