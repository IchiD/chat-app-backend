<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $groups = $user->ownedGroups()->with('members')->get();
        return response()->json($groups);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $maxAllowed = $user->plan === 'premium' ? 200 : 50;
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'max_members' => 'integer|min:1|max:' . $maxAllowed,
        ]);
        $data['owner_user_id'] = Auth::id();
        $group = Group::create($data);
        return response()->json($group, 201);
    }

    public function show(Group $group)
    {
        $user = Auth::user();
        $isMember = $group->members()->where('user_id', $user->id)->exists();
        if ($group->owner_user_id !== $user->id && !$isMember) {
            return response()->json(['message' => '権限がありません。'], 403);
        }
        $group->load('members');
        return response()->json($group);
    }

    public function update(Request $request, Group $group)
    {
        $user = Auth::user();
        if ($group->owner_user_id !== $user->id) {
            return response()->json(['message' => '権限がありません。'], 403);
        }
        $maxAllowed = $user->plan === 'premium' ? 200 : 50;
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'max_members' => 'integer|min:1|max:' . $maxAllowed,
        ]);
        $group->update($data);
        return response()->json($group);
    }

    public function destroy(Group $group)
    {
        $user = Auth::user();
        if ($group->owner_user_id !== $user->id) {
            return response()->json(['message' => '権限がありません。'], 403);
        }
        $group->delete();
        return response()->json(['message' => 'deleted']);
    }
}
