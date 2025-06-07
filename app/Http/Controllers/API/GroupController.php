<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $groups = $user->ownedGroups()->withCount('members')->get();
        return response()->json($groups);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'max_members' => 'nullable|integer|min:1|max:500',
        ]);
        $user = Auth::user();
        $group = null;
        DB::transaction(function () use ($user, $request, &$group) {
            $group = Group::create([
                'owner_user_id' => $user->id,
                'name' => $request->name,
                'description' => $request->description,
                'max_members' => $request->max_members ?? 50,
            ]);
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'nickname' => $user->name,
            ]);
        });
        return response()->json($group, 201);
    }

    public function show(Group $group)
    {
        $user = Auth::user();
        if ($group->owner_user_id !== $user->id) {
            return response()->json(['message' => __('errors.forbidden')], 403);
        }
        $group->load('members');
        return response()->json($group);
    }

    public function update(Group $group, Request $request)
    {
        $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'max_members' => 'nullable|integer|min:1|max:500',
        ]);
        $user = Auth::user();
        if ($group->owner_user_id !== $user->id) {
            return response()->json(['message' => __('errors.forbidden')], 403);
        }
        $group->update($request->only('name', 'description', 'max_members'));
        return response()->json($group);
    }

    public function destroy(Group $group)
    {
        $user = Auth::user();
        if ($group->owner_user_id !== $user->id) {
            return response()->json(['message' => __('errors.forbidden')], 403);
        }
        $group->delete();
        return response()->json(['message' => __('messages.group_deleted')]);
    }

    public function addMember(Group $group, Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'nickname' => 'nullable|string|max:50',
        ]);
        $user = Auth::user();
        if ($group->owner_user_id !== $user->id) {
            return response()->json(['message' => __('errors.forbidden')], 403);
        }
        if ($group->members()->where('user_id', $request->user_id)->exists()) {
            return response()->json(['message' => __('errors.already_member')], 422);
        }
        $member = GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $request->user_id,
            'nickname' => $request->nickname ?? 'member',
        ]);
        return response()->json($member, 201);
    }

    public function removeMember(Group $group, GroupMember $member)
    {
        $user = Auth::user();
        if ($group->owner_user_id !== $user->id) {
            return response()->json(['message' => __('errors.forbidden')], 403);
        }
        if ($member->group_id !== $group->id) {
            return response()->json(['message' => __('errors.invalid_member')], 422);
        }
        $member->delete();
        return response()->json(['message' => __('messages.member_removed')]);
    }

    public function getQrCode(Group $group)
    {
        $user = Auth::user();
        if ($group->owner_user_id !== $user->id) {
            return response()->json(['message' => __('errors.forbidden')], 403);
        }
        return response()->json(['qr_code_token' => $group->qr_code_token]);
    }

    public function regenerateQrCode(Group $group)
    {
        $user = Auth::user();
        if ($group->owner_user_id !== $user->id) {
            return response()->json(['message' => __('errors.forbidden')], 403);
        }
        $group->regenerateQrToken();
        return response()->json(['qr_code_token' => $group->qr_code_token]);
    }

    public function joinByToken(Request $request, string $token)
    {
        $request->validate([
            'nickname' => 'required|string|max:50',
        ]);

        $group = Group::where('qr_code_token', $token)->firstOrFail();

        // メンバー数制限チェック
        if ($group->members()->count() >= $group->max_members) {
            return response()->json(['message' => __('errors.group_full')], 422);
        }

        $user = Auth::user();
        if ($user && $group->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => __('errors.already_member')], 422);
        }

        $member = GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user?->id,
            'nickname' => $request->nickname,
        ]);

        return response()->json($member, 201);
    }
}
